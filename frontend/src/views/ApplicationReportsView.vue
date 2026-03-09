<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import api from '../api/axios'

const auth = useAuthStore()

const parkId = ref<number | null>(auth.parks[0]?.id ?? null)
const dateFrom = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const dateTo = ref(new Date().toISOString().slice(0, 10))

// --- Application metrics ---
interface AppReportData {
  total: number
  completed: number
  avg_days_to_complete: number | null
  by_status: { status: string; count: number }[]
}

const appData = ref<AppReportData | null>(null)
const appLoading = ref(false)

async function loadAppReport() {
  appLoading.value = true
  try {
    const res = await api.get<AppReportData>('/reports/applications', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    appData.value = res.data
  } catch {
    appData.value = { total: 0, completed: 0, avg_days_to_complete: null, by_status: [] }
  } finally {
    appLoading.value = false
  }
}

const conversionRate = computed(() => {
  if (!appData.value || appData.value.total === 0) return '–'
  return ((appData.value.completed / appData.value.total) * 100).toFixed(1) + '%'
})

function exportAppExcel() {
  const params = new URLSearchParams({
    format: 'xlsx',
    from: dateFrom.value,
    to: dateTo.value,
    ...(parkId.value ? { park_id: String(parkId.value) } : {}),
  })
  window.open(`/api/reports/applications?${params}`, '_blank')
}

// --- Waiting list metrics ---
interface WaitingListReport {
  total: number
  avg_wait_days: number | null
  by_unit_type: { unit_type: string; count: number; avg_wait_days: number | null }[]
}

const waitData = ref<WaitingListReport | null>(null)
const waitLoading = ref(false)

async function loadWaitingListReport() {
  waitLoading.value = true
  try {
    const res = await api.get<WaitingListReport>('/reports/waiting-list', {
      params: { park_id: parkId.value },
    })
    waitData.value = res.data
  } catch {
    waitData.value = { total: 0, avg_wait_days: null, by_unit_type: [] }
  } finally {
    waitLoading.value = false
  }
}

async function loadAll() {
  await Promise.all([loadAppReport(), loadWaitingListReport()])
}

watch([parkId, dateFrom, dateTo], loadAll)
onMounted(loadAll)

// Status bar chart
const barW = 360
const barH = 140
const bPadTop = 16
const bPadBottom = 28
const bPadLeft = 60
const bPadRight = 12

const barData = computed(() => {
  const items = appData.value?.by_status ?? []
  if (!items.length) return []
  const max = Math.max(...items.map((d) => d.count), 1)
  const availH = barH - bPadTop - bPadBottom
  const availW = barW - bPadLeft - bPadRight
  const bw = Math.min(36, availW / items.length - 6)
  return items.map((d, i) => ({
    label: d.status,
    count: d.count,
    x: bPadLeft + i * (availW / items.length) + (availW / items.length - bw) / 2,
    y: bPadTop + availH - (d.count / max) * availH,
    h: (d.count / max) * availH,
    w: bw,
    lx: bPadLeft + i * (availW / items.length) + availW / items.length / 2,
    ly: barH - bPadBottom + 14,
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
function barColor(s: string) { return statusColors[s] ?? '#64748b' }
</script>

<template>
  <div class="app-reports">
    <div class="page-header">
      <h1 class="page-title">Anfragen-Reports</h1>
    </div>

    <!-- Filters -->
    <div class="filters-row">
      <select v-model="parkId" class="filter-ctrl">
        <option :value="null">Alle Parks</option>
        <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <input v-model="dateFrom" class="filter-ctrl" type="date" />
      <input v-model="dateTo" class="filter-ctrl" type="date" />
      <button class="export-btn" @click="exportAppExcel">↓ Excel exportieren</button>
    </div>

    <!-- Application metrics section -->
    <div class="section-title">Anfragen</div>

    <div v-if="appLoading" class="loading-state">Lade...</div>
    <template v-else>
      <div class="metric-row">
        <div class="metric-card">
          <div class="metric-value">{{ appData?.total ?? '–' }}</div>
          <div class="metric-label">Anfragen gesamt</div>
        </div>
        <div class="metric-card highlight">
          <div class="metric-value">{{ conversionRate }}</div>
          <div class="metric-label">Konversionsrate</div>
        </div>
        <div class="metric-card">
          <div class="metric-value">{{ appData?.completed ?? '–' }}</div>
          <div class="metric-label">Abgeschlossen</div>
        </div>
        <div class="metric-card">
          <div class="metric-value">
            {{ appData?.avg_days_to_complete != null ? appData.avg_days_to_complete.toFixed(1) + ' Tage' : '–' }}
          </div>
          <div class="metric-label">Ø Bearbeitungszeit (Neu → Abschluss)</div>
        </div>
      </div>

      <!-- Status breakdown -->
      <div class="card">
        <div class="card-title">Aufschlüsselung nach Status</div>
        <div v-if="!barData.length" class="chart-empty">Keine Daten</div>
        <div v-else class="chart-row">
          <svg :width="barW" :height="barH">
            <line :x1="bPadLeft - 4" :y1="bPadTop" :x2="bPadLeft - 4" :y2="barH - bPadBottom" stroke="#e2e8f0" stroke-width="1" />
            <line :x1="bPadLeft - 4" :y1="barH - bPadBottom" :x2="barW - bPadRight" :y2="barH - bPadBottom" stroke="#e2e8f0" stroke-width="1" />
            <g v-for="bar in barData" :key="bar.label">
              <rect :x="bar.x" :y="bar.y" :width="bar.w" :height="bar.h" :fill="barColor(bar.label)" rx="3" />
              <text :x="bar.lx" :y="bar.ly" font-size="9" fill="#64748b" text-anchor="middle">{{ bar.label }}</text>
              <text :x="bar.x + bar.w / 2" :y="bar.y - 4" font-size="9" fill="#374151" text-anchor="middle">{{ bar.count }}</text>
            </g>
          </svg>

          <table class="status-table">
            <thead>
              <tr><th>Status</th><th>Anzahl</th><th>Anteil</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in (appData?.by_status ?? [])" :key="row.status">
                <td>
                  <span class="status-dot" :style="{ background: barColor(row.status) }"></span>
                  {{ row.status }}
                </td>
                <td class="num">{{ row.count }}</td>
                <td class="num pct">
                  {{ appData && appData.total > 0 ? ((row.count / appData.total) * 100).toFixed(1) + '%' : '–' }}
                </td>
              </tr>
              <tr v-if="!(appData?.by_status ?? []).length">
                <td colspan="3" class="empty">Keine Daten</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Waiting list section -->
    <div class="section-title">Warteliste</div>

    <div v-if="waitLoading" class="loading-state">Lade...</div>
    <template v-else>
      <div class="metric-row">
        <div class="metric-card">
          <div class="metric-value">{{ waitData?.total ?? '–' }}</div>
          <div class="metric-label">Einträge gesamt</div>
        </div>
        <div class="metric-card highlight">
          <div class="metric-value">
            {{ waitData?.avg_wait_days != null ? waitData.avg_wait_days.toFixed(1) + ' Tage' : '–' }}
          </div>
          <div class="metric-label">Ø Wartezeit</div>
        </div>
        <div class="metric-card">
          <div class="metric-value">{{ (waitData?.by_unit_type ?? []).length }}</div>
          <div class="metric-label">Einheitentypen</div>
        </div>
      </div>

      <div class="card">
        <div class="card-title">Einträge nach Einheitentyp</div>
        <table class="status-table">
          <thead>
            <tr><th>Einheitentyp</th><th>Einträge</th><th>Ø Wartezeit</th></tr>
          </thead>
          <tbody>
            <tr v-for="row in (waitData?.by_unit_type ?? [])" :key="row.unit_type">
              <td>{{ row.unit_type }}</td>
              <td class="num">{{ row.count }}</td>
              <td class="num">{{ row.avg_wait_days != null ? row.avg_wait_days.toFixed(1) + ' Tage' : '–' }}</td>
            </tr>
            <tr v-if="!(waitData?.by_unit_type ?? []).length">
              <td colspan="3" class="empty">Keine Daten</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.app-reports {
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

.section-title {
  font-size: 1rem;
  font-weight: 600;
  color: #374151;
  padding-bottom: 0.25rem;
  border-bottom: 1px solid #e2e8f0;
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

.card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem;
}

.card-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 0.875rem;
}

.chart-row {
  display: flex;
  gap: 2rem;
  align-items: flex-start;
  flex-wrap: wrap;
}

.chart-empty {
  font-size: 0.875rem;
  color: #94a3b8;
  padding: 1rem 0;
  text-align: center;
}

.status-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.status-table th {
  text-align: left;
  padding: 0.5rem 0.75rem;
  background: #f8fafc;
  font-weight: 600;
  color: #374151;
  border-bottom: 1px solid #e2e8f0;
}

.status-table td {
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #f1f5f9;
  color: #374151;
}

.status-table td.num {
  text-align: right;
  font-weight: 500;
  color: #1e293b;
}

.status-table td.pct {
  color: #64748b;
  font-weight: 400;
}

.status-table td.empty {
  text-align: center;
  color: #94a3b8;
  padding: 1rem;
}

.status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 6px;
}

.loading-state {
  padding: 2rem;
  text-align: center;
  color: #64748b;
  font-size: 0.875rem;
}
</style>
