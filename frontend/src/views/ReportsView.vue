<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import api from '../api/axios'

const auth = useAuthStore()

type Tab = 'applications' | 'customers' | 'units' | 'finance' | 'audit'
const activeTab = ref<Tab>('applications')

// --- Filters ---
const parkId = ref<number | null>(auth.parks[0]?.id ?? null)
const dateFrom = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const dateTo = ref(new Date().toISOString().slice(0, 10))

// --- Applications tab data ---
interface AppStats {
  total: number
  completed: number
  avg_processing_days: number | null
  by_status: { status: string; count: number }[]
  by_source: { source: string; count: number }[]
}
const appStats = ref<AppStats | null>(null)
const appLoading = ref(false)

async function loadAppStats() {
  appLoading.value = true
  try {
    const res = await api.get<AppStats>('/reports/applications', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    appStats.value = res.data
  } catch {
    appStats.value = {
      total: 0,
      completed: 0,
      avg_processing_days: null,
      by_status: [],
      by_source: [],
    }
  } finally {
    appLoading.value = false
  }
}

const conversionRate = computed(() => {
  if (!appStats.value || appStats.value.total === 0) return '–'
  return ((appStats.value.completed / appStats.value.total) * 100).toFixed(1) + '%'
})

// Bar chart for applications by status
const barChartWidth = 400
const barChartHeight = 160
const barPaddingTop = 20
const barPaddingBottom = 30
const barPaddingLeft = 80
const barPaddingRight = 16

const barData = computed(() => {
  const items = appStats.value?.by_status ?? []
  if (!items.length) return []
  const max = Math.max(...items.map((d) => d.count), 1)
  const availH = barChartHeight - barPaddingTop - barPaddingBottom
  const availW = barChartWidth - barPaddingLeft - barPaddingRight
  const bw = Math.min(40, availW / items.length - 6)
  return items.map((d, i) => ({
    label: d.status,
    count: d.count,
    x: barPaddingLeft + i * (availW / items.length) + (availW / items.length - bw) / 2,
    y: barPaddingTop + availH - (d.count / max) * availH,
    h: (d.count / max) * availH,
    w: bw,
    lx: barPaddingLeft + i * (availW / items.length) + availW / items.length / 2,
    ly: barChartHeight - barPaddingBottom + 14,
  }))
})

const statusColors: Record<string, string> = {
  new: '#3b82f6',
  in_review: '#f59e0b',
  waiting: '#8b5cf6',
  completed: '#22c55e',
  rejected: '#ef4444',
  withdrawn: '#94a3b8',
}

function barColor(status: string): string {
  return statusColors[status] ?? '#64748b'
}

// --- Customers tab data ---
interface CustomerStats {
  churn_rate: number | null
  by_month: { month: string; new_count: number; active_count: number; inactive_count: number }[]
  by_status: { status: string; count: number }[]
  by_park: { park_name: string; count: number }[]
}
const custStats = ref<CustomerStats | null>(null)
const custLoading = ref(false)

async function loadCustStats() {
  custLoading.value = true
  try {
    const res = await api.get<CustomerStats>('/reports/customers', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    custStats.value = res.data
  } catch {
    custStats.value = {
      churn_rate: null,
      by_month: [],
      by_status: [],
      by_park: [],
    }
  } finally {
    custLoading.value = false
  }
}

// Line chart
const lineChartWidth = 480
const lineChartHeight = 180
const linePadTop = 16
const linePadBottom = 40
const linePadLeft = 40
const linePadRight = 16

interface LinePoint { x: number; y: number }

function buildLinePoints(values: number[]): LinePoint[] {
  if (!values.length) return []
  const max = Math.max(...values, 1)
  const availW = lineChartWidth - linePadLeft - linePadRight
  const availH = lineChartHeight - linePadTop - linePadBottom
  return values.map((v, i) => ({
    x: linePadLeft + (i / Math.max(values.length - 1, 1)) * availW,
    y: linePadTop + availH - (v / max) * availH,
  }))
}

function pointsToPath(pts: LinePoint[]): string {
  if (!pts.length) return ''
  return pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
}

const lineNew = computed(() => buildLinePoints((custStats.value?.by_month ?? []).map((m) => m.new_count)))
const lineActive = computed(() => buildLinePoints((custStats.value?.by_month ?? []).map((m) => m.active_count)))
const lineInactive = computed(() => buildLinePoints((custStats.value?.by_month ?? []).map((m) => m.inactive_count)))

const lineXLabels = computed(() => {
  const months = custStats.value?.by_month ?? []
  if (!months.length) return []
  const availW = lineChartWidth - linePadLeft - linePadRight
  return months.map((m, i) => ({
    label: m.month.slice(0, 7),
    x: linePadLeft + (i / Math.max(months.length - 1, 1)) * availW,
    y: lineChartHeight - linePadBottom + 16,
  }))
})

// Donut chart
const donutCx = 90
const donutCy = 90
const donutR = 60
const donutHole = 36

const donutSegments = computed(() => {
  const items = custStats.value?.by_status ?? []
  const total = items.reduce((s, d) => s + d.count, 0)
  if (!total) return []
  const colors = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#94a3b8']
  let angle = -Math.PI / 2
  return items.map((d, i) => {
    const slice = (d.count / total) * 2 * Math.PI
    const x1 = donutCx + donutR * Math.cos(angle)
    const y1 = donutCy + donutR * Math.sin(angle)
    angle += slice
    const x2 = donutCx + donutR * Math.cos(angle)
    const y2 = donutCy + donutR * Math.sin(angle)
    const xi1 = donutCx + donutHole * Math.cos(angle)
    const yi1 = donutCy + donutHole * Math.sin(angle)
    const xi2 = donutCx + donutHole * Math.cos(angle - slice)
    const yi2 = donutCy + donutHole * Math.sin(angle - slice)
    const large = slice > Math.PI ? 1 : 0
    const path = `M${x1.toFixed(2)},${y1.toFixed(2)} A${donutR},${donutR} 0 ${large},1 ${x2.toFixed(2)},${y2.toFixed(2)} L${xi1.toFixed(2)},${yi1.toFixed(2)} A${donutHole},${donutHole} 0 ${large},0 ${xi2.toFixed(2)},${yi2.toFixed(2)} Z`
    return { path, color: colors[i % colors.length], label: d.status, count: d.count, pct: ((d.count / total) * 100).toFixed(1) }
  })
})

// --- Export ---
function exportExcel(type: 'applications' | 'customers') {
  const params = new URLSearchParams({
    format: 'xlsx',
    ...(parkId.value ? { park_id: String(parkId.value) } : {}),
    from: dateFrom.value,
    to: dateTo.value,
  })
  window.open(`/api/reports/${type}?${params}`, '_blank')
}

// --- Units tab data ---
interface UnitStats {
  total: number
  rented: number
  avg_rent_per_sqm: number | null
  vacant_units: { id: number; number: string; type: string; vacant_days: number }[]
  top_damaged: { id: number; number: string; damage_count: number }[]
}
const unitStats = ref<UnitStats | null>(null)
const unitLoading = ref(false)

async function loadUnitStats() {
  unitLoading.value = true
  try {
    const res = await api.get<UnitStats>('/reports/units', {
      params: { park_id: parkId.value },
    })
    unitStats.value = res.data
  } catch {
    unitStats.value = { total: 0, rented: 0, avg_rent_per_sqm: null, vacant_units: [], top_damaged: [] }
  } finally {
    unitLoading.value = false
  }
}

const occupancyRate = computed(() => {
  if (!unitStats.value || unitStats.value.total === 0) return '–'
  return ((unitStats.value.rented / unitStats.value.total) * 100).toFixed(1) + '%'
})

// Occupancy donut (2 segments: rented + vacant)
const occupancyDonut = computed(() => {
  const s = unitStats.value
  if (!s || s.total === 0) return []
  const items = [
    { label: 'Vermietet', count: s.rented, color: '#22c55e' },
    { label: 'Leer', count: s.total - s.rented, color: '#f1f5f9' },
  ]
  const total = s.total
  const cx = 60; const cy = 60; const r = 50; const hole = 30
  let angle = -Math.PI / 2
  return items.map((d) => {
    const slice = (d.count / total) * 2 * Math.PI
    const x1 = cx + r * Math.cos(angle)
    const y1 = cy + r * Math.sin(angle)
    angle += slice
    const x2 = cx + r * Math.cos(angle)
    const y2 = cy + r * Math.sin(angle)
    const xi1 = cx + hole * Math.cos(angle)
    const yi1 = cy + hole * Math.sin(angle)
    const xi2 = cx + hole * Math.cos(angle - slice)
    const yi2 = cy + hole * Math.sin(angle - slice)
    const large = slice > Math.PI ? 1 : 0
    const path = `M${x1.toFixed(2)},${y1.toFixed(2)} A${r},${r} 0 ${large},1 ${x2.toFixed(2)},${y2.toFixed(2)} L${xi1.toFixed(2)},${yi1.toFixed(2)} A${hole},${hole} 0 ${large},0 ${xi2.toFixed(2)},${yi2.toFixed(2)} Z`
    return { path, color: d.color, label: d.label, count: d.count }
  })
})

// --- Finance tab data ---
interface FinanceStats {
  outstanding_debt: number
  by_month: { month: string; revenue: number; plan: number }[]
  by_payment_method: { method: string; count: number; total: number }[]
  top_debtors: { customer_name: string; amount: number }[]
}
const finStats = ref<FinanceStats | null>(null)
const finLoading = ref(false)
const datevModalOpen = ref(false)
const datevFrom = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const datevTo = ref(new Date().toISOString().slice(0, 10))

async function loadFinStats() {
  finLoading.value = true
  try {
    const res = await api.get<FinanceStats>('/reports/finance', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    finStats.value = res.data
  } catch {
    finStats.value = { outstanding_debt: 0, by_month: [], by_payment_method: [], top_debtors: [] }
  } finally {
    finLoading.value = false
  }
}

// Revenue vs plan bar chart (grouped)
const revBarWidth = 480
const revBarHeight = 180
const revPadTop = 20
const revPadBottom = 40
const revPadLeft = 60
const revPadRight = 16

const revBarData = computed(() => {
  const items = finStats.value?.by_month ?? []
  if (!items.length) return []
  const allVals = items.flatMap((d) => [d.revenue, d.plan])
  const max = Math.max(...allVals, 1)
  const availH = revBarHeight - revPadTop - revPadBottom
  const availW = revBarWidth - revPadLeft - revPadRight
  const groupW = availW / items.length
  const bw = Math.min(18, groupW / 2 - 2)
  return items.map((d, i) => {
    const gx = revPadLeft + i * groupW
    const rh = (d.revenue / max) * availH
    const ph = (d.plan / max) * availH
    return {
      label: d.month.slice(0, 7),
      lx: gx + groupW / 2,
      ly: revBarHeight - revPadBottom + 14,
      revX: gx + groupW / 2 - bw - 1,
      revY: revPadTop + availH - rh,
      revH: rh,
      planX: gx + groupW / 2 + 1,
      planY: revPadTop + availH - ph,
      planH: ph,
      bw,
    }
  })
})

// Payments pie chart (same donut formula)
const payDonutCx = 70; const payDonutCy = 70; const payDonutR = 55; const payDonutHole = 28
const payColors = ['#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6', '#ef4444', '#94a3b8']

const payDonut = computed(() => {
  const items = finStats.value?.by_payment_method ?? []
  const total = items.reduce((s, d) => s + d.count, 0)
  if (!total) return []
  let angle = -Math.PI / 2
  return items.map((d, i) => {
    const slice = (d.count / total) * 2 * Math.PI
    const x1 = payDonutCx + payDonutR * Math.cos(angle)
    const y1 = payDonutCy + payDonutR * Math.sin(angle)
    angle += slice
    const x2 = payDonutCx + payDonutR * Math.cos(angle)
    const y2 = payDonutCy + payDonutR * Math.sin(angle)
    const xi1 = payDonutCx + payDonutHole * Math.cos(angle)
    const yi1 = payDonutCy + payDonutHole * Math.sin(angle)
    const xi2 = payDonutCx + payDonutHole * Math.cos(angle - slice)
    const yi2 = payDonutCy + payDonutHole * Math.sin(angle - slice)
    const large = slice > Math.PI ? 1 : 0
    const path = `M${x1.toFixed(2)},${y1.toFixed(2)} A${payDonutR},${payDonutR} 0 ${large},1 ${x2.toFixed(2)},${y2.toFixed(2)} L${xi1.toFixed(2)},${yi1.toFixed(2)} A${payDonutHole},${payDonutHole} 0 ${large},0 ${xi2.toFixed(2)},${yi2.toFixed(2)} Z`
    return { path, color: payColors[i % payColors.length], label: d.method, count: d.count, pct: ((d.count / total) * 100).toFixed(1) }
  })
})

function exportDatev() {
  const params = new URLSearchParams({
    format: 'datev',
    from: datevFrom.value,
    to: datevTo.value,
    ...(parkId.value ? { park_id: String(parkId.value) } : {}),
  })
  window.open(`/api/reports/finance?${params}`, '_blank')
  datevModalOpen.value = false
}

// --- Audit tab data ---
interface AuditEntry {
  id: number
  user_name: string | null
  action: string
  model_type: string
  model_id: number | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  created_at: string
}
interface AuditPage {
  data: AuditEntry[]
  total: number
  last_page: number
}

const auditEntries = ref<AuditEntry[]>([])
const auditTotal = ref(0)
const auditLastPage = ref(1)
const auditPage = ref(1)
const auditLoading = ref(false)
const auditUserFilter = ref('')
const auditModelType = ref('')
const auditFrom = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const auditTo = ref(new Date().toISOString().slice(0, 10))
const auditUsers = ref<{ id: number; name: string }[]>([])
const expandedAuditId = ref<number | null>(null)

const modelTypes = ['Application', 'Contract', 'Customer', 'Invoice', 'Payment', 'DamageReport', 'Task', 'Park', 'Unit', 'User']

async function loadAuditUsers() {
  try {
    const res = await api.get<{ data: { id: number; name: string }[] }>('/admin/users', { params: { per_page: 200 } })
    auditUsers.value = res.data.data ?? []
  } catch {
    auditUsers.value = []
  }
}

async function loadAuditLog() {
  auditLoading.value = true
  try {
    const res = await api.get<AuditPage>('/audit-log', {
      params: {
        page: auditPage.value,
        per_page: 20,
        user: auditUserFilter.value || undefined,
        model_type: auditModelType.value || undefined,
        from: auditFrom.value,
        to: auditTo.value,
      },
    })
    auditEntries.value = res.data.data
    auditTotal.value = res.data.total
    auditLastPage.value = res.data.last_page
  } catch {
    auditEntries.value = []
    auditTotal.value = 0
    auditLastPage.value = 1
  } finally {
    auditLoading.value = false
  }
}

function toggleAuditRow(id: number) {
  expandedAuditId.value = expandedAuditId.value === id ? null : id
}

function jsonSummary(vals: Record<string, unknown> | null): string {
  if (!vals) return '–'
  const keys = Object.keys(vals)
  if (!keys.length) return '–'
  return keys.slice(0, 3).map((k) => `${k}: ${String(vals[k]).slice(0, 20)}`).join(', ') + (keys.length > 3 ? '…' : '')
}

function exportAuditCsv() {
  const params = new URLSearchParams({
    format: 'csv',
    from: auditFrom.value,
    to: auditTo.value,
    ...(auditUserFilter.value ? { user: auditUserFilter.value } : {}),
    ...(auditModelType.value ? { model_type: auditModelType.value } : {}),
  })
  window.open(`/api/audit-log?${params}`, '_blank')
}

watch([auditUserFilter, auditModelType, auditFrom, auditTo], () => {
  auditPage.value = 1
  loadAuditLog()
})

watch(auditPage, loadAuditLog)

// --- Load on mount and filter change ---
function loadForTab() {
  if (activeTab.value === 'applications') loadAppStats()
  else if (activeTab.value === 'customers') loadCustStats()
  else if (activeTab.value === 'units') loadUnitStats()
  else if (activeTab.value === 'finance') loadFinStats()
  else if (activeTab.value === 'audit') { loadAuditUsers(); loadAuditLog() }
}

watch([activeTab, parkId, dateFrom, dateTo], loadForTab)
onMounted(loadForTab)
</script>

<template>
  <div class="reports">
    <div class="page-header">
      <h1 class="page-title">Reports</h1>
    </div>

    <!-- Tab bar -->
    <div class="tab-bar">
      <button
        v-for="tab in (['applications', 'customers', 'units', 'finance'] as const)"
        :key="tab"
        class="tab-btn"
        :class="{ active: activeTab === tab }"
        @click="activeTab = tab"
      >
        {{ { applications: 'Anfragen', customers: 'Kunden', units: 'Einheiten', finance: 'Finanzen' }[tab] }}
      </button>
      <button
        v-if="auth.role === 'admin'"
        class="tab-btn"
        :class="{ active: activeTab === 'audit' }"
        @click="activeTab = 'audit'"
      >
        Audit
      </button>
    </div>

    <!-- Applications Tab -->
    <div v-if="activeTab === 'applications'" class="tab-content">
      <!-- Filters -->
      <div class="filters-row">
        <select v-model="parkId" class="filter-ctrl">
          <option :value="null">Alle Parks</option>
          <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <input v-model="dateFrom" class="filter-ctrl" type="date" />
        <input v-model="dateTo" class="filter-ctrl" type="date" />
        <button class="export-btn" @click="exportExcel('applications')">
          ↓ Excel exportieren
        </button>
      </div>

      <div v-if="appLoading" class="loading-state">Lade...</div>

      <template v-else>
        <!-- Metric cards -->
        <div class="metric-row">
          <div class="metric-card">
            <div class="metric-value">{{ appStats?.total ?? '–' }}</div>
            <div class="metric-label">Anfragen gesamt</div>
          </div>
          <div class="metric-card highlight">
            <div class="metric-value">{{ conversionRate }}</div>
            <div class="metric-label">Konversionsrate</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">
              {{ appStats?.avg_processing_days != null ? appStats.avg_processing_days.toFixed(1) + ' Tage' : '–' }}
            </div>
            <div class="metric-label">Ø Bearbeitungszeit</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ appStats?.completed ?? '–' }}</div>
            <div class="metric-label">Abgeschlossen</div>
          </div>
        </div>

        <div class="charts-row">
          <!-- Bar chart: by status -->
          <div class="chart-card">
            <div class="chart-title">Anfragen nach Status</div>
            <div v-if="!barData.length" class="chart-empty">Keine Daten</div>
            <svg v-else :width="barChartWidth" :height="barChartHeight" class="bar-chart">
              <g v-for="bar in barData" :key="bar.label">
                <rect
                  :x="bar.x"
                  :y="bar.y"
                  :width="bar.w"
                  :height="bar.h"
                  :fill="barColor(bar.label)"
                  rx="3"
                />
                <text :x="bar.lx" :y="bar.ly" class="bar-label" text-anchor="middle" font-size="10" fill="#64748b">
                  {{ bar.label }}
                </text>
                <text :x="bar.x + bar.w / 2" :y="bar.y - 4" text-anchor="middle" font-size="10" fill="#374151">
                  {{ bar.count }}
                </text>
              </g>
              <!-- Y axis line -->
              <line
                :x1="barPaddingLeft - 4" :y1="barPaddingTop"
                :x2="barPaddingLeft - 4" :y2="barChartHeight - barPaddingBottom"
                stroke="#e2e8f0" stroke-width="1"
              />
              <line
                :x1="barPaddingLeft - 4" :y1="barChartHeight - barPaddingBottom"
                :x2="barChartWidth - barPaddingRight" :y2="barChartHeight - barPaddingBottom"
                stroke="#e2e8f0" stroke-width="1"
              />
            </svg>
          </div>

          <!-- Top sources table -->
          <div class="chart-card">
            <div class="chart-title">Top Quellen</div>
            <table class="mini-table">
              <thead>
                <tr><th>Quelle</th><th>Anzahl</th></tr>
              </thead>
              <tbody>
                <tr v-for="row in (appStats?.by_source ?? [])" :key="row.source">
                  <td>{{ row.source }}</td>
                  <td class="num">{{ row.count }}</td>
                </tr>
                <tr v-if="!(appStats?.by_source ?? []).length">
                  <td colspan="2" class="empty">Keine Daten</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- Customers Tab -->
    <div v-if="activeTab === 'customers'" class="tab-content">
      <!-- Filters -->
      <div class="filters-row">
        <select v-model="parkId" class="filter-ctrl">
          <option :value="null">Alle Parks</option>
          <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <input v-model="dateFrom" class="filter-ctrl" type="date" />
        <input v-model="dateTo" class="filter-ctrl" type="date" />
        <button class="export-btn" @click="exportExcel('customers')">
          ↓ Excel exportieren
        </button>
      </div>

      <div v-if="custLoading" class="loading-state">Lade...</div>

      <template v-else>
        <!-- Metric cards -->
        <div class="metric-row">
          <div class="metric-card highlight">
            <div class="metric-value">
              {{ custStats?.churn_rate != null ? custStats.churn_rate.toFixed(1) + '%' : '–' }}
            </div>
            <div class="metric-label">Churn Rate</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ (custStats?.by_status ?? []).reduce((s, x) => s + x.count, 0) || '–' }}</div>
            <div class="metric-label">Kunden gesamt</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">
              {{ (custStats?.by_status ?? []).find((x) => x.status === 'active')?.count ?? '–' }}
            </div>
            <div class="metric-label">Aktiv</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ custStats?.by_month?.length ?? 0 }} Monate</div>
            <div class="metric-label">Zeitraum</div>
          </div>
        </div>

        <div class="charts-row">
          <!-- Line chart: by month -->
          <div class="chart-card wide">
            <div class="chart-title">Kunden-Entwicklung nach Monat</div>
            <div class="line-legend">
              <span class="legend-dot" style="background:#3b82f6"></span> Neu
              <span class="legend-dot" style="background:#22c55e"></span> Aktiv
              <span class="legend-dot" style="background:#94a3b8"></span> Inaktiv
            </div>
            <div v-if="!(custStats?.by_month ?? []).length" class="chart-empty">Keine Daten</div>
            <svg v-else :width="lineChartWidth" :height="lineChartHeight" class="line-chart">
              <!-- Grid lines -->
              <line
                :x1="linePadLeft" :y1="linePadTop"
                :x2="linePadLeft" :y2="lineChartHeight - linePadBottom"
                stroke="#e2e8f0" stroke-width="1"
              />
              <line
                :x1="linePadLeft" :y1="lineChartHeight - linePadBottom"
                :x2="lineChartWidth - linePadRight" :y2="lineChartHeight - linePadBottom"
                stroke="#e2e8f0" stroke-width="1"
              />
              <!-- Lines -->
              <path v-if="lineNew.length" :d="pointsToPath(lineNew)" fill="none" stroke="#3b82f6" stroke-width="2" />
              <path v-if="lineActive.length" :d="pointsToPath(lineActive)" fill="none" stroke="#22c55e" stroke-width="2" />
              <path v-if="lineInactive.length" :d="pointsToPath(lineInactive)" fill="none" stroke="#94a3b8" stroke-width="2" />
              <!-- Dots -->
              <circle v-for="(pt, i) in lineNew" :key="'n'+i" :cx="pt.x" :cy="pt.y" r="3" fill="#3b82f6" />
              <circle v-for="(pt, i) in lineActive" :key="'a'+i" :cx="pt.x" :cy="pt.y" r="3" fill="#22c55e" />
              <circle v-for="(pt, i) in lineInactive" :key="'i'+i" :cx="pt.x" :cy="pt.y" r="3" fill="#94a3b8" />
              <!-- X labels (show every 2nd) -->
              <text
                v-for="(lb, i) in lineXLabels.filter((_, j) => j % 2 === 0)"
                :key="i"
                :x="lb.x"
                :y="lb.y"
                font-size="9"
                fill="#94a3b8"
                text-anchor="middle"
              >{{ lb.label }}</text>
            </svg>
          </div>

          <!-- Donut chart: by status -->
          <div class="chart-card donut-card">
            <div class="chart-title">Status-Verteilung</div>
            <div v-if="!donutSegments.length" class="chart-empty">Keine Daten</div>
            <div v-else class="donut-wrap">
              <svg :width="donutCx * 2" :height="donutCy * 2">
                <path
                  v-for="(seg, i) in donutSegments"
                  :key="i"
                  :d="seg.path"
                  :fill="seg.color"
                />
              </svg>
              <div class="donut-legend">
                <div v-for="seg in donutSegments" :key="seg.label" class="donut-legend-item">
                  <span class="legend-dot" :style="{ background: seg.color }"></span>
                  <span class="legend-lbl">{{ seg.label }}</span>
                  <span class="legend-pct">{{ seg.pct }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Customers by park table -->
        <div class="chart-card mt">
          <div class="chart-title">Kunden nach Park</div>
          <table class="mini-table">
            <thead>
              <tr><th>Park</th><th>Kunden</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in (custStats?.by_park ?? [])" :key="row.park_name">
                <td>{{ row.park_name }}</td>
                <td class="num">{{ row.count }}</td>
              </tr>
              <tr v-if="!(custStats?.by_park ?? []).length">
                <td colspan="2" class="empty">Keine Daten</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- Units Tab -->
    <div v-if="activeTab === 'units'" class="tab-content">
      <div class="filters-row">
        <select v-model="parkId" class="filter-ctrl">
          <option :value="null">Alle Parks</option>
          <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>

      <div v-if="unitLoading" class="loading-state">Lade...</div>
      <template v-else>
        <!-- Metrics -->
        <div class="metric-row">
          <div class="metric-card">
            <div class="metric-value">{{ unitStats?.total ?? '–' }}</div>
            <div class="metric-label">Einheiten gesamt</div>
          </div>
          <div class="metric-card highlight">
            <div class="metric-value">{{ occupancyRate }}</div>
            <div class="metric-label">Auslastungsrate</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">
              {{ unitStats?.avg_rent_per_sqm != null ? '€' + unitStats.avg_rent_per_sqm.toFixed(2) : '–' }}
            </div>
            <div class="metric-label">Ø Miete / m²</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ unitStats ? unitStats.total - unitStats.rented : '–' }}</div>
            <div class="metric-label">Leerstand</div>
          </div>
        </div>

        <div class="charts-row">
          <!-- Occupancy donut -->
          <div class="chart-card donut-card">
            <div class="chart-title">Belegung</div>
            <div v-if="!occupancyDonut.length" class="chart-empty">Keine Daten</div>
            <div v-else class="donut-wrap">
              <svg width="120" height="120">
                <path v-for="(seg, i) in occupancyDonut" :key="i" :d="seg.path" :fill="seg.color" />
              </svg>
              <div class="donut-legend">
                <div v-for="seg in occupancyDonut" :key="seg.label" class="donut-legend-item">
                  <span class="legend-dot" :style="{ background: seg.color, border: seg.color === '#f1f5f9' ? '1px solid #e2e8f0' : 'none' }"></span>
                  <span class="legend-lbl">{{ seg.label }}</span>
                  <span class="legend-pct">{{ seg.count }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Top damaged units -->
          <div class="chart-card">
            <div class="chart-title">Top Schadenseinheiten</div>
            <table class="mini-table">
              <thead>
                <tr><th>Einheit</th><th>Schäden</th></tr>
              </thead>
              <tbody>
                <tr v-for="row in (unitStats?.top_damaged ?? [])" :key="row.id">
                  <td>{{ row.number }}</td>
                  <td class="num">{{ row.damage_count }}</td>
                </tr>
                <tr v-if="!(unitStats?.top_damaged ?? []).length">
                  <td colspan="2" class="empty">Keine Daten</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Vacant units table -->
        <div class="chart-card">
          <div class="chart-title">Leerstehende Einheiten (nach Dauer)</div>
          <table class="mini-table">
            <thead>
              <tr><th>Einheit</th><th>Typ</th><th>Leer seit (Tage)</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in (unitStats?.vacant_units ?? [])" :key="row.id">
                <td>{{ row.number }}</td>
                <td>{{ row.type }}</td>
                <td class="num">{{ row.vacant_days }}</td>
              </tr>
              <tr v-if="!(unitStats?.vacant_units ?? []).length">
                <td colspan="3" class="empty">Keine Daten</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- Finance Tab -->
    <div v-if="activeTab === 'finance'" class="tab-content">
      <div class="filters-row">
        <select v-model="parkId" class="filter-ctrl">
          <option :value="null">Alle Parks</option>
          <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <input v-model="dateFrom" class="filter-ctrl" type="date" />
        <input v-model="dateTo" class="filter-ctrl" type="date" />
        <button class="export-btn datev-btn" @click="datevModalOpen = true">
          ↓ DATEV Export
        </button>
        <button class="export-btn" @click="exportExcel('finance' as 'applications')">
          ↓ Excel exportieren
        </button>
      </div>

      <div v-if="finLoading" class="loading-state">Lade...</div>
      <template v-else>
        <!-- Metrics -->
        <div class="metric-row">
          <div class="metric-card highlight red">
            <div class="metric-value">
              {{ finStats?.outstanding_debt != null ? '€' + finStats.outstanding_debt.toLocaleString('de-DE', { minimumFractionDigits: 2 }) : '–' }}
            </div>
            <div class="metric-label">Offene Forderungen</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ finStats?.top_debtors?.length ?? 0 }}</div>
            <div class="metric-label">Schuldner</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ (finStats?.by_month ?? []).length }}</div>
            <div class="metric-label">Monate im Zeitraum</div>
          </div>
        </div>

        <div class="charts-row">
          <!-- Revenue vs plan bar chart -->
          <div class="chart-card wide">
            <div class="chart-title">Umsatz vs. Planung (monatlich)</div>
            <div class="line-legend">
              <span class="legend-dot" style="background:#3b82f6"></span> Umsatz
              <span class="legend-dot" style="background:#e2e8f0; border:1px solid #94a3b8"></span> Planung
            </div>
            <div v-if="!revBarData.length" class="chart-empty">Keine Daten</div>
            <svg v-else :width="revBarWidth" :height="revBarHeight" class="bar-chart">
              <line :x1="revPadLeft - 4" :y1="revPadTop" :x2="revPadLeft - 4" :y2="revBarHeight - revPadBottom" stroke="#e2e8f0" stroke-width="1" />
              <line :x1="revPadLeft - 4" :y1="revBarHeight - revPadBottom" :x2="revBarWidth - revPadRight" :y2="revBarHeight - revPadBottom" stroke="#e2e8f0" stroke-width="1" />
              <g v-for="bar in revBarData" :key="bar.label">
                <rect :x="bar.revX" :y="bar.revY" :width="bar.bw" :height="bar.revH" fill="#3b82f6" rx="2" />
                <rect :x="bar.planX" :y="bar.planY" :width="bar.bw" :height="bar.planH" fill="#e2e8f0" rx="2" />
                <text :x="bar.lx" :y="bar.ly" font-size="8" fill="#94a3b8" text-anchor="middle">{{ bar.label }}</text>
              </g>
            </svg>
          </div>

          <!-- Payments pie -->
          <div class="chart-card donut-card">
            <div class="chart-title">Zahlungsarten</div>
            <div v-if="!payDonut.length" class="chart-empty">Keine Daten</div>
            <div v-else class="donut-wrap">
              <svg :width="payDonutCx * 2" :height="payDonutCy * 2">
                <path v-for="(seg, i) in payDonut" :key="i" :d="seg.path" :fill="seg.color" />
              </svg>
              <div class="donut-legend">
                <div v-for="seg in payDonut" :key="seg.label" class="donut-legend-item">
                  <span class="legend-dot" :style="{ background: seg.color }"></span>
                  <span class="legend-lbl">{{ seg.label }}</span>
                  <span class="legend-pct">{{ seg.pct }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Top debtors -->
        <div class="chart-card">
          <div class="chart-title">Top Schuldner</div>
          <table class="mini-table">
            <thead>
              <tr><th>Kunde</th><th>Betrag</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in (finStats?.top_debtors ?? [])" :key="row.customer_name">
                <td>{{ row.customer_name }}</td>
                <td class="num">€{{ row.amount.toLocaleString('de-DE', { minimumFractionDigits: 2 }) }}</td>
              </tr>
              <tr v-if="!(finStats?.top_debtors ?? []).length">
                <td colspan="2" class="empty">Keine Daten</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- Audit Tab (Admin only) -->
    <div v-if="activeTab === 'audit' && auth.role === 'admin'" class="tab-content">
      <div class="filters-row">
        <select v-model="auditUserFilter" class="filter-ctrl">
          <option value="">Alle Nutzer</option>
          <option v-for="u in auditUsers" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
        </select>
        <select v-model="auditModelType" class="filter-ctrl">
          <option value="">Alle Entitäten</option>
          <option v-for="mt in modelTypes" :key="mt" :value="mt">{{ mt }}</option>
        </select>
        <input v-model="auditFrom" class="filter-ctrl" type="date" />
        <input v-model="auditTo" class="filter-ctrl" type="date" />
        <button class="export-btn" @click="exportAuditCsv">↓ CSV exportieren</button>
      </div>

      <div v-if="auditLoading" class="loading-state">Lade...</div>
      <template v-else>
        <div class="chart-card" style="overflow-x: auto">
          <table class="audit-table">
            <thead>
              <tr>
                <th>Nutzer</th>
                <th>Aktion</th>
                <th>Entität</th>
                <th>ID</th>
                <th>Alt (Zusammenfassung)</th>
                <th>Neu (Zusammenfassung)</th>
                <th>IP</th>
                <th>Zeitpunkt</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="entry in auditEntries" :key="entry.id">
                <tr class="audit-row" :class="{ expanded: expandedAuditId === entry.id }" @click="toggleAuditRow(entry.id)">
                  <td>{{ entry.user_name ?? '–' }}</td>
                  <td><span class="action-badge" :class="entry.action">{{ entry.action }}</span></td>
                  <td>{{ entry.model_type }}</td>
                  <td class="num">{{ entry.model_id ?? '–' }}</td>
                  <td class="summary-cell">{{ jsonSummary(entry.old_values) }}</td>
                  <td class="summary-cell">{{ jsonSummary(entry.new_values) }}</td>
                  <td class="mono">{{ entry.ip_address ?? '–' }}</td>
                  <td class="mono">{{ new Date(entry.created_at).toLocaleString('de-DE') }}</td>
                </tr>
                <tr v-if="expandedAuditId === entry.id" class="audit-detail-row">
                  <td colspan="8">
                    <div class="diff-wrap">
                      <div class="diff-col">
                        <div class="diff-label">Alte Werte</div>
                        <pre class="diff-pre old">{{ entry.old_values ? JSON.stringify(entry.old_values, null, 2) : '–' }}</pre>
                      </div>
                      <div class="diff-col">
                        <div class="diff-label">Neue Werte</div>
                        <pre class="diff-pre new">{{ entry.new_values ? JSON.stringify(entry.new_values, null, 2) : '–' }}</pre>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="!auditEntries.length">
                <td colspan="8" class="empty">Keine Einträge</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="auditLastPage > 1" class="audit-pagination">
          <button class="page-btn" :disabled="auditPage <= 1" @click="auditPage--">‹</button>
          <span class="page-info">Seite {{ auditPage }} / {{ auditLastPage }} ({{ auditTotal }} Einträge)</span>
          <button class="page-btn" :disabled="auditPage >= auditLastPage" @click="auditPage++">›</button>
        </div>
      </template>
    </div>

    <!-- DATEV Export Modal -->
    <Teleport to="body">
      <div v-if="datevModalOpen" class="modal-backdrop" @click.self="datevModalOpen = false">
        <div class="modal">
          <div class="modal-header">
            <h3>DATEV Export</h3>
            <button class="close-btn" @click="datevModalOpen = false">✕</button>
          </div>
          <div class="modal-body">
            <div class="form-grid">
              <div class="form-row">
                <label>Von</label>
                <input v-model="datevFrom" class="form-ctrl" type="date" />
              </div>
              <div class="form-row">
                <label>Bis</label>
                <input v-model="datevTo" class="form-ctrl" type="date" />
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn-secondary" @click="datevModalOpen = false">Abbrechen</button>
            <button class="btn-primary" @click="exportDatev">DATEV herunterladen</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.reports {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.page-title {
  font-size: 1.375rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.tab-bar {
  display: flex;
  gap: 0;
  border-bottom: 2px solid #e2e8f0;
}

.tab-btn {
  padding: 0.625rem 1.25rem;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  font-size: 0.875rem;
  color: #64748b;
  cursor: pointer;
  transition: color 0.15s, border-color 0.15s;
  font-weight: 500;
}

.tab-btn:hover {
  color: #1e293b;
}

.tab-btn.active {
  color: #3b82f6;
  border-bottom-color: #3b82f6;
}

.tab-content {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.filters-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.filter-ctrl {
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 0.4375rem 0.75rem;
  font-size: 0.875rem;
  color: #1e293b;
  background: #fff;
  outline: none;
}

.filter-ctrl:focus {
  border-color: #3b82f6;
}

.export-btn {
  margin-left: auto;
  padding: 0.4375rem 1rem;
  background: #f8fafc;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  font-size: 0.875rem;
  color: #374151;
  cursor: pointer;
  transition: background 0.15s;
}

.export-btn:hover {
  background: #f1f5f9;
}

.metric-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1rem;
}

.metric-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem 1.5rem;
}

.metric-card.highlight {
  border-color: #bfdbfe;
  background: #eff6ff;
}

.metric-value {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.1;
}

.metric-label {
  font-size: 0.8125rem;
  color: #64748b;
  margin-top: 0.25rem;
}

.charts-row {
  display: flex;
  gap: 1.25rem;
  flex-wrap: wrap;
}

.chart-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem;
  flex: 1;
  min-width: 260px;
}

.chart-card.wide {
  flex: 2;
  min-width: 320px;
}

.chart-card.donut-card {
  min-width: 260px;
  max-width: 320px;
}

.chart-card.mt {
  margin-top: 0;
}

.chart-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.875rem;
}

.chart-empty {
  font-size: 0.875rem;
  color: #94a3b8;
  padding: 1rem 0;
  text-align: center;
}

.bar-chart,
.line-chart {
  display: block;
  overflow: visible;
}

.bar-label {
  font-size: 10px;
}

.line-legend {
  display: flex;
  gap: 1rem;
  font-size: 0.8125rem;
  color: #64748b;
  margin-bottom: 0.5rem;
  align-items: center;
}

.legend-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-right: 4px;
}

.donut-wrap {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.donut-legend {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  font-size: 0.8125rem;
}

.donut-legend-item {
  display: flex;
  align-items: center;
  gap: 0.375rem;
}

.legend-lbl {
  flex: 1;
  color: #374151;
}

.legend-pct {
  color: #64748b;
  font-size: 0.75rem;
}

.mini-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.mini-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  background: #f8fafc;
  font-weight: 600;
  color: #374151;
  border-bottom: 1px solid #e2e8f0;
}

.mini-table td {
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
  color: #374151;
}

.mini-table td.num {
  text-align: right;
  font-weight: 500;
  color: #1e293b;
}

.mini-table td.empty {
  text-align: center;
  color: #94a3b8;
  padding: 1rem;
}

.loading-state {
  padding: 2rem;
  text-align: center;
  color: #64748b;
  font-size: 0.875rem;
}

.placeholder {
  padding: 2rem;
  background: #f8fafc;
  border: 1px dashed #e2e8f0;
  border-radius: 8px;
  color: #94a3b8;
  font-size: 0.875rem;
}

.metric-card.highlight.red {
  border-color: #fecaca;
  background: #fef2f2;
}

.datev-btn {
  background: #f0fdf4;
  border-color: #bbf7d0;
  color: #15803d;
}

.datev-btn:hover {
  background: #dcfce7;
}

/* DATEV modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15);
  min-width: 360px;
  max-width: 90vw;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #e2e8f0;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: #1e293b;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1rem;
  color: #94a3b8;
  cursor: pointer;
  padding: 0.25rem;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  padding: 1rem 1.5rem;
  border-top: 1px solid #e2e8f0;
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

.form-grid {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
}

.form-row {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.form-row label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: #374151;
}

.form-ctrl {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 0.5rem 0.625rem;
  font-size: 0.875rem;
  color: #1e293b;
  outline: none;
  background: #fff;
  box-sizing: border-box;
}

.form-ctrl:focus {
  border-color: #3b82f6;
}

.btn-primary {
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-secondary {
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  color: #64748b;
  cursor: pointer;
}

.btn-secondary:hover {
  background: #f1f5f9;
}

/* Audit table */
.audit-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.8125rem;
  min-width: 900px;
}

.audit-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  background: #f8fafc;
  font-weight: 600;
  color: #374151;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}

.audit-row {
  cursor: pointer;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.1s;
}

.audit-row:hover,
.audit-row.expanded {
  background: #f8fafc;
}

.audit-row td {
  padding: 0.5rem 0.75rem;
  color: #374151;
  vertical-align: top;
}

.audit-detail-row td {
  padding: 0;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.diff-wrap {
  display: flex;
  gap: 0;
}

.diff-col {
  flex: 1;
  padding: 0.75rem 1rem;
  border-right: 1px solid #e2e8f0;
}

.diff-col:last-child {
  border-right: none;
}

.diff-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 0.375rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.diff-pre {
  font-size: 0.75rem;
  font-family: monospace;
  white-space: pre-wrap;
  word-break: break-word;
  margin: 0;
  padding: 0.625rem;
  border-radius: 6px;
  max-height: 240px;
  overflow-y: auto;
}

.diff-pre.old {
  background: #fef2f2;
  color: #991b1b;
}

.diff-pre.new {
  background: #f0fdf4;
  color: #166534;
}

.action-badge {
  display: inline-block;
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

.action-badge.created { background: #dcfce7; color: #166534; }
.action-badge.updated { background: #dbeafe; color: #1e40af; }
.action-badge.deleted { background: #fee2e2; color: #991b1b; }

.summary-cell {
  max-width: 180px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #64748b;
  font-size: 0.75rem;
}

.mono {
  font-family: monospace;
  font-size: 0.75rem;
  color: #64748b;
}

.audit-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 0.75rem 0;
}

.page-btn {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
  cursor: pointer;
  transition: background 0.15s;
}

.page-btn:hover:not(:disabled) {
  background: #f1f5f9;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.page-info {
  font-size: 0.8125rem;
  color: #64748b;
}
</style>
