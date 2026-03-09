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

// --- Load on mount and filter change ---
function loadForTab() {
  if (activeTab.value === 'applications') loadAppStats()
  else if (activeTab.value === 'customers') loadCustStats()
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
        v-for="tab in (['applications', 'customers', 'units', 'finance', 'audit'] as const)"
        :key="tab"
        class="tab-btn"
        :class="{ active: activeTab === tab }"
        @click="activeTab = tab"
      >
        {{ { applications: 'Anfragen', customers: 'Kunden', units: 'Einheiten', finance: 'Finanzen', audit: 'Audit' }[tab] }}
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

    <!-- Placeholder tabs -->
    <div v-if="activeTab === 'units'" class="tab-content placeholder">
      <p>Einheiten-Reports werden in US-078 implementiert.</p>
    </div>
    <div v-if="activeTab === 'finance'" class="tab-content placeholder">
      <p>Finanz-Reports werden in US-078 implementiert.</p>
    </div>
    <div v-if="activeTab === 'audit'" class="tab-content placeholder">
      <p>Audit-Log wird in US-079 implementiert.</p>
    </div>
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
</style>
