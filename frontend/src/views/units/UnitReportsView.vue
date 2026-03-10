<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import api from '../../api/axios'

const auth = useAuthStore()

const parkId = ref<number | null>(auth.parks[0]?.id ?? null)
const dateFrom = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const dateTo = ref(new Date().toISOString().slice(0, 10))

interface UnitDetailReport {
  by_status: { status: string; count: number }[]
  vacancy_by_type: { unit_type: string; avg_vacancy_days: number }[]
  damage_by_status: { status: string; count: number }[]
  repair_cost_total: number
  repair_cost_by_type: { unit_type: string; total: number }[]
}

const data = ref<UnitDetailReport | null>(null)
const loading = ref(false)

async function loadReport() {
  loading.value = true
  try {
    const res = await api.get<UnitDetailReport>('/reports/units/detail', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    data.value = res.data
  } catch {
    data.value = { by_status: [], vacancy_by_type: [], damage_by_status: [], repair_cost_total: 0, repair_cost_by_type: [] }
  } finally {
    loading.value = false
  }
}

function exportExcel() {
  const params = new URLSearchParams({
    format: 'xlsx', from: dateFrom.value, to: dateTo.value,
    ...(parkId.value ? { park_id: String(parkId.value) } : {}),
  })
  window.open(`/api/reports/units?${params}`, '_blank')
}

watch([parkId, dateFrom, dateTo], loadReport)
onMounted(loadReport)

const damageColors: Record<string, string> = {
  open: '#ef4444', in_progress: '#f59e0b', resolved: '#22c55e', closed: '#94a3b8',
}
function dmgColor(s: string) { return damageColors[s] ?? '#64748b' }

const unitStatusColors: Record<string, string> = {
  available: '#22c55e', rented: '#3b82f6', maintenance: '#f59e0b', deactivated: '#94a3b8',
}
function unitColor(s: string) { return unitStatusColors[s] ?? '#64748b' }
</script>

<template>
  <div class="reports-page">
    <h1 class="page-title">Einheiten-Reports</h1>

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
      <!-- Unit status metrics -->
      <div class="section-title">Einheiten nach Status</div>
      <div class="metric-row">
        <div v-for="row in (data?.by_status ?? [])" :key="row.status" class="metric-card" :style="{ borderColor: unitColor(row.status) + '66' }">
          <div class="metric-value" :style="{ color: unitColor(row.status) }">{{ row.count }}</div>
          <div class="metric-label">{{ row.status }}</div>
        </div>
      </div>

      <!-- Damage metrics -->
      <div class="section-title">Schäden</div>
      <div class="metric-row">
        <div v-for="row in (data?.damage_by_status ?? [])" :key="row.status" class="metric-card">
          <div class="metric-value" :style="{ color: dmgColor(row.status) }">{{ row.count }}</div>
          <div class="metric-label">{{ row.status }}</div>
        </div>
        <div class="metric-card highlight-red">
          <div class="metric-value">
            {{ data?.repair_cost_total != null ? '€' + data.repair_cost_total.toLocaleString('de-DE', { minimumFractionDigits: 2 }) : '–' }}
          </div>
          <div class="metric-label">Reparaturkosten gesamt</div>
        </div>
      </div>

      <div class="cards-row">
        <!-- Avg vacancy by type -->
        <div class="card">
          <div class="card-title">Ø Leerstandsdauer nach Einheitentyp</div>
          <table class="report-table">
            <thead>
              <tr><th>Einheitentyp</th><th>Ø Leerstand (Tage)</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in (data?.vacancy_by_type ?? [])" :key="row.unit_type">
                <td>{{ row.unit_type }}</td>
                <td class="num">{{ row.avg_vacancy_days.toFixed(1) }}</td>
              </tr>
              <tr v-if="!(data?.vacancy_by_type ?? []).length">
                <td colspan="2" class="empty">Keine Daten</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Repair cost by type -->
        <div class="card">
          <div class="card-title">Reparaturkosten nach Einheitentyp</div>
          <table class="report-table">
            <thead>
              <tr><th>Einheitentyp</th><th>Kosten</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in (data?.repair_cost_by_type ?? [])" :key="row.unit_type">
                <td>{{ row.unit_type }}</td>
                <td class="num">€{{ row.total.toLocaleString('de-DE', { minimumFractionDigits: 2 }) }}</td>
              </tr>
              <tr v-if="!(data?.repair_cost_by_type ?? []).length">
                <td colspan="2" class="empty">Keine Daten</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.reports-page { display: flex; flex-direction: column; gap: 1.25rem; }
.page-title { font-size: 1.375rem; font-weight: 700; color: #1e293b; margin: 0; }
.section-title { font-size: 1rem; font-weight: 600; color: #374151; padding-bottom: 0.25rem; border-bottom: 1px solid #e2e8f0; }
.filters-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.filter-ctrl { border: 1px solid #d1d5db; border-radius: 6px; padding: 0.4375rem 0.75rem; font-size: 0.875rem; color: #1e293b; background: #fff; outline: none; }
.filter-ctrl:focus { border-color: #3b82f6; }
.export-btn { margin-left: auto; padding: 0.4375rem 1rem; background: #f8fafc; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; color: #374151; cursor: pointer; }
.export-btn:hover { background: #f1f5f9; }
.metric-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; }
.metric-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; }
.metric-card.highlight-red { border-color: #fecaca; background: #fef2f2; }
.metric-value { font-size: 1.75rem; font-weight: 700; color: #1e293b; line-height: 1.1; }
.metric-label { font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem; }
.cards-row { display: flex; gap: 1.25rem; flex-wrap: wrap; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; flex: 1; min-width: 260px; }
.card-title { font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.875rem; }
.report-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.report-table th { text-align: left; padding: 0.5rem 0.75rem; background: #f8fafc; font-weight: 600; color: #374151; border-bottom: 1px solid #e2e8f0; }
.report-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f1f5f9; color: #374151; }
.report-table td.num { text-align: right; font-weight: 500; color: #1e293b; }
.report-table td.empty { text-align: center; color: #94a3b8; padding: 1rem; }
.loading-state { padding: 2rem; text-align: center; color: #64748b; font-size: 0.875rem; }
</style>
