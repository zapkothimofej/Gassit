<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import api from '../api/axios'

const auth = useAuthStore()

const parkId = ref<number | null>(auth.parks[0]?.id ?? null)
const dateFrom = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const dateTo = ref(new Date().toISOString().slice(0, 10))

interface CustomerReport {
  by_status: { status: string; count: number }[]
  blacklist_count: number
  gdpr_deletion_count: number
  new_per_month: { month: string; count: number }[]
}

const data = ref<CustomerReport | null>(null)
const loading = ref(false)

async function loadReport() {
  loading.value = true
  try {
    const res = await api.get<CustomerReport>('/reports/customers/detail', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    data.value = res.data
  } catch {
    data.value = { by_status: [], blacklist_count: 0, gdpr_deletion_count: 0, new_per_month: [] }
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  const params = new URLSearchParams({
    format: 'xlsx', from: dateFrom.value, to: dateTo.value,
    ...(parkId.value ? { park_id: String(parkId.value) } : {}),
  })
  window.open(`/api/reports/customers?${params}`, '_blank')
}

watch([parkId, dateFrom, dateTo], loadReport)
onMounted(loadReport)

// Line chart for new customers per month
const lcW = 400; const lcH = 140; const lcPT = 16; const lcPB = 32; const lcPL = 40; const lcPR = 12

function buildPoints(values: number[]): { x: number; y: number }[] {
  if (!values.length) return []
  const max = Math.max(...values, 1)
  const availW = lcW - lcPL - lcPR
  const availH = lcH - lcPT - lcPB
  return values.map((v, i) => ({
    x: lcPL + (i / Math.max(values.length - 1, 1)) * availW,
    y: lcPT + availH - (v / max) * availH,
  }))
}

function toPath(pts: { x: number; y: number }[]): string {
  return pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
}

function xLabels(months: { month: string; count: number }[]): { x: number; label: string }[] {
  const availW = lcW - lcPL - lcPR
  return months
    .filter((_, j) => j % 2 === 0)
    .map((m, i) => {
      const origIdx = i * 2
      return {
        x: lcPL + (origIdx / Math.max(months.length - 1, 1)) * availW,
        label: m.month.slice(0, 7),
      }
    })
}
</script>

<template>
  <div class="reports-page">
    <h1 class="page-title">Kunden-Reports</h1>

    <div class="filters-row">
      <select v-model="parkId" class="filter-ctrl">
        <option :value="null">Alle Parks</option>
        <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <input v-model="dateFrom" class="filter-ctrl" type="date" />
      <input v-model="dateTo" class="filter-ctrl" type="date" />
      <button class="export-btn" @click="exportExcel">↓ Excel exportieren</button>
    </div>

    <div v-if="loading" class="loading-state">Lade...</div>
    <template v-else>
      <!-- Metric cards -->
      <div class="metric-row">
        <div v-for="row in (data?.by_status ?? [])" :key="row.status" class="metric-card">
          <div class="metric-value">{{ row.count }}</div>
          <div class="metric-label">{{ row.status }}</div>
        </div>
        <div class="metric-card warn">
          <div class="metric-value">{{ data?.blacklist_count ?? '–' }}</div>
          <div class="metric-label">Blacklist</div>
        </div>
        <div class="metric-card">
          <div class="metric-value">{{ data?.gdpr_deletion_count ?? '–' }}</div>
          <div class="metric-label">DSGVO-Löschungen</div>
        </div>
      </div>

      <!-- New customers per month line chart -->
      <div class="card">
        <div class="card-title">Neue Kunden pro Monat</div>
        <div v-if="!(data?.new_per_month ?? []).length" class="chart-empty">Keine Daten</div>
        <svg v-else :width="lcW" :height="lcH">
          <line :x1="lcPL - 4" :y1="lcPT" :x2="lcPL - 4" :y2="lcH - lcPB" stroke="#e2e8f0" stroke-width="1" />
          <line :x1="lcPL - 4" :y1="lcH - lcPB" :x2="lcW - lcPR" :y2="lcH - lcPB" stroke="#e2e8f0" stroke-width="1" />
          <path :d="toPath(buildPoints((data?.new_per_month ?? []).map((m) => m.count)))" fill="none" stroke="#3b82f6" stroke-width="2" />
          <circle
            v-for="(pt, i) in buildPoints((data?.new_per_month ?? []).map((m) => m.count))"
            :key="i" :cx="pt.x" :cy="pt.y" r="3" fill="#3b82f6"
          />
          <text
            v-for="lb in xLabels(data?.new_per_month ?? [])"
            :key="lb.label"
            :x="lb.x" :y="lcH - lcPB + 14"
            font-size="9" fill="#94a3b8" text-anchor="middle"
          >{{ lb.label }}</text>
        </svg>
      </div>
    </template>
  </div>
</template>

<style scoped>
.reports-page { display: flex; flex-direction: column; gap: 1.25rem; }
.page-title { font-size: 1.375rem; font-weight: 700; color: #1e293b; margin: 0; }
.filters-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.filter-ctrl { border: 1px solid #d1d5db; border-radius: 6px; padding: 0.4375rem 0.75rem; font-size: 0.875rem; color: #1e293b; background: #fff; outline: none; }
.filter-ctrl:focus { border-color: #3b82f6; }
.export-btn { margin-left: auto; padding: 0.4375rem 1rem; background: #f8fafc; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; color: #374151; cursor: pointer; }
.export-btn:hover { background: #f1f5f9; }
.metric-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; }
.metric-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; }
.metric-card.warn { border-color: #fde68a; background: #fffbeb; }
.metric-value { font-size: 1.75rem; font-weight: 700; color: #1e293b; line-height: 1.1; }
.metric-label { font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
.card-title { font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.875rem; }
.chart-empty { font-size: 0.875rem; color: #94a3b8; padding: 1rem 0; text-align: center; }
.loading-state { padding: 2rem; text-align: center; color: #64748b; font-size: 0.875rem; }
</style>
