<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import api from '../../api/axios'

const auth = useAuthStore()

type Tab = 'revenue' | 'debtors' | 'payments' | 'vendors'
const activeTab = ref<Tab>('revenue')

const parkId = ref<number | null>(auth.parks[0]?.id ?? null)
const dateFrom = ref(new Date(new Date().getFullYear(), 0, 1).toISOString().slice(0, 10))
const dateTo = ref(new Date().toISOString().slice(0, 10))

// --- Revenue tab ---
interface RevenueRow { month: string; plan: number; actual: number; diff: number }
const revData = ref<{ rows: RevenueRow[]; year_total: number } | null>(null)
const revLoading = ref(false)

async function loadRevenue() {
  revLoading.value = true
  try {
    const res = await api.get<{ rows: RevenueRow[]; year_total: number }>('/reports/finance/revenue', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    revData.value = res.data
  } catch {
    revData.value = { rows: [], year_total: 0 }
  } finally {
    revLoading.value = false
  }
}

// --- Debtors tab ---
interface DebtorRow {
  customer_name: string
  dunning_level: number
  bucket_0_30: number
  bucket_31_60: number
  bucket_60plus: number
  total_owed: number
}
const debtorData = ref<{ rows: DebtorRow[]; total_owed: number } | null>(null)
const debtorLoading = ref(false)

async function loadDebtors() {
  debtorLoading.value = true
  try {
    const res = await api.get<{ rows: DebtorRow[]; total_owed: number }>('/reports/finance/debtors', {
      params: { park_id: parkId.value },
    })
    debtorData.value = res.data
  } catch {
    debtorData.value = { rows: [], total_owed: 0 }
  } finally {
    debtorLoading.value = false
  }
}

// --- Payments tab ---
interface PaymentStats {
  by_method: { method: string; count: number; success_count: number; failed_count: number }[]
  avg_payment_days: number | null
}
const payData = ref<PaymentStats | null>(null)
const payLoading = ref(false)

async function loadPayments() {
  payLoading.value = true
  try {
    const res = await api.get<PaymentStats>('/reports/finance/payments', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    payData.value = res.data
  } catch {
    payData.value = { by_method: [], avg_payment_days: null }
  } finally {
    payLoading.value = false
  }
}

// --- Vendors tab ---
interface VendorStats {
  by_park: { park_name: string; total: number }[]
  by_specialty: { specialty: string; total: number }[]
  by_month: { month: string; total: number }[]
}
const vendorData = ref<VendorStats | null>(null)
const vendorLoading = ref(false)

async function loadVendors() {
  vendorLoading.value = true
  try {
    const res = await api.get<VendorStats>('/reports/finance/vendors', {
      params: { park_id: parkId.value, from: dateFrom.value, to: dateTo.value },
    })
    vendorData.value = res.data
  } catch {
    vendorData.value = { by_park: [], by_specialty: [], by_month: [] }
  } finally {
    vendorLoading.value = false
  }
}

function exportExcel() {
  const params = new URLSearchParams({
    format: 'xlsx', tab: activeTab.value,
    from: dateFrom.value, to: dateTo.value,
    ...(parkId.value ? { park_id: String(parkId.value) } : {}),
  })
  window.open(`/api/reports/finance?${params}`, '_blank')
}

function loadForTab() {
  if (activeTab.value === 'revenue') loadRevenue()
  else if (activeTab.value === 'debtors') loadDebtors()
  else if (activeTab.value === 'payments') loadPayments()
  else if (activeTab.value === 'vendors') loadVendors()
}

watch([activeTab, parkId, dateFrom, dateTo], loadForTab)
onMounted(loadForTab)

// Payment bar chart
const pbW = 360; const pbH = 140; const pbPT = 16; const pbPB = 28; const pbPL = 40; const pbPR = 12

function barPts(items: { method: string; count: number }[]) {
  if (!items.length) return []
  const max = Math.max(...items.map((d) => d.count), 1)
  const availH = pbH - pbPT - pbPB
  const availW = pbW - pbPL - pbPR
  const bw = Math.min(40, availW / items.length - 6)
  return items.map((d, i) => ({
    label: d.method,
    count: d.count,
    x: pbPL + i * (availW / items.length) + (availW / items.length - bw) / 2,
    y: pbPT + availH - (d.count / max) * availH,
    h: (d.count / max) * availH,
    w: bw,
    lx: pbPL + i * (availW / items.length) + availW / items.length / 2,
  }))
}

const payColors = ['#3b82f6', '#22c55e', '#f59e0b', '#8b5cf6', '#ef4444']

function fmt(n: number) {
  return '€' + n.toLocaleString('de-DE', { minimumFractionDigits: 2 })
}
</script>

<template>
  <div class="fin-reports">
    <h1 class="page-title">Finanz-Reports</h1>

    <!-- Tab bar -->
    <div class="tab-bar">
      <button
        v-for="tab in (['revenue', 'debtors', 'payments', 'vendors'] as const)"
        :key="tab"
        class="tab-btn"
        :class="{ active: activeTab === tab }"
        @click="activeTab = tab"
      >
        {{ { revenue: 'Umsatz', debtors: 'Schuldner', payments: 'Zahlungen', vendors: 'Lieferanten' }[tab] }}
      </button>
    </div>

    <!-- Filters -->
    <div class="filters-row">
      <select v-model="parkId" class="filter-ctrl">
        <option :value="null">Alle Parks</option>
        <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <input v-model="dateFrom" class="filter-ctrl" type="date" />
      <input v-model="dateTo" class="filter-ctrl" type="date" />
      <button class="export-btn" @click="exportExcel">↓ Excel exportieren</button>
    </div>

    <!-- Revenue tab -->
    <div v-if="activeTab === 'revenue'">
      <div v-if="revLoading" class="loading-state">Lade...</div>
      <template v-else>
        <div class="metric-row" v-if="revData">
          <div class="metric-card highlight">
            <div class="metric-value">{{ fmt(revData.year_total) }}</div>
            <div class="metric-label">Jahres-Umsatz gesamt</div>
          </div>
        </div>
        <div class="card">
          <div class="card-title">Monatlicher Umsatz: Plan vs. Ist</div>
          <table class="report-table">
            <thead>
              <tr><th>Monat</th><th class="r">Plan</th><th class="r">Ist</th><th class="r">Differenz</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in (revData?.rows ?? [])" :key="row.month">
                <td>{{ row.month }}</td>
                <td class="r">{{ fmt(row.plan) }}</td>
                <td class="r">{{ fmt(row.actual) }}</td>
                <td class="r" :class="row.diff >= 0 ? 'pos' : 'neg'">
                  {{ row.diff >= 0 ? '+' : '' }}{{ fmt(row.diff) }}
                </td>
              </tr>
              <tr v-if="!(revData?.rows ?? []).length">
                <td colspan="4" class="empty">Keine Daten</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- Debtors tab -->
    <div v-if="activeTab === 'debtors'">
      <div v-if="debtorLoading" class="loading-state">Lade...</div>
      <template v-else>
        <div class="metric-row" v-if="debtorData">
          <div class="metric-card highlight-red">
            <div class="metric-value">{{ fmt(debtorData.total_owed) }}</div>
            <div class="metric-label">Offene Forderungen gesamt</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">{{ debtorData.rows.length }}</div>
            <div class="metric-label">Schuldner</div>
          </div>
        </div>
        <div class="card">
          <div class="card-title">Schuldner nach Altersbuckets</div>
          <table class="report-table">
            <thead>
              <tr><th>Kunde</th><th>Mahnstufe</th><th class="r">0–30 Tage</th><th class="r">31–60 Tage</th><th class="r">60+ Tage</th><th class="r">Gesamt</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in (debtorData?.rows ?? [])" :key="row.customer_name">
                <td>{{ row.customer_name }}</td>
                <td><span class="level-badge">{{ row.dunning_level }}</span></td>
                <td class="r">{{ row.bucket_0_30 > 0 ? fmt(row.bucket_0_30) : '–' }}</td>
                <td class="r">{{ row.bucket_31_60 > 0 ? fmt(row.bucket_31_60) : '–' }}</td>
                <td class="r neg">{{ row.bucket_60plus > 0 ? fmt(row.bucket_60plus) : '–' }}</td>
                <td class="r bold">{{ fmt(row.total_owed) }}</td>
              </tr>
              <tr v-if="!(debtorData?.rows ?? []).length">
                <td colspan="6" class="empty">Keine Daten</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>

    <!-- Payments tab -->
    <div v-if="activeTab === 'payments'">
      <div v-if="payLoading" class="loading-state">Lade...</div>
      <template v-else>
        <div class="metric-row">
          <div class="metric-card">
            <div class="metric-value">
              {{ payData?.avg_payment_days != null ? payData.avg_payment_days.toFixed(1) + ' Tage' : '–' }}
            </div>
            <div class="metric-label">Ø Zahlungsdauer</div>
          </div>
          <div class="metric-card">
            <div class="metric-value">
              {{ (payData?.by_method ?? []).reduce((s, x) => s + x.success_count, 0) }}
            </div>
            <div class="metric-label">Erfolgreiche Zahlungen</div>
          </div>
          <div class="metric-card highlight-red">
            <div class="metric-value">
              {{ (payData?.by_method ?? []).reduce((s, x) => s + x.failed_count, 0) }}
            </div>
            <div class="metric-label">Fehlgeschlagene Zahlungen</div>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Zahlungen nach Methode</div>
          <div v-if="!(payData?.by_method ?? []).length" class="chart-empty">Keine Daten</div>
          <div v-else class="chart-row">
            <svg :width="pbW" :height="pbH">
              <line :x1="pbPL-4" :y1="pbPT" :x2="pbPL-4" :y2="pbH-pbPB" stroke="#e2e8f0" stroke-width="1" />
              <line :x1="pbPL-4" :y1="pbH-pbPB" :x2="pbW-pbPR" :y2="pbH-pbPB" stroke="#e2e8f0" stroke-width="1" />
              <g v-for="(bar, i) in barPts(payData?.by_method ?? [])" :key="bar.label">
                <rect :x="bar.x" :y="bar.y" :width="bar.w" :height="bar.h" :fill="payColors[i % payColors.length]" rx="3" />
                <text :x="bar.lx" :y="pbH-pbPB+14" font-size="9" fill="#64748b" text-anchor="middle">{{ bar.label }}</text>
                <text :x="bar.x + bar.w/2" :y="bar.y-4" font-size="9" fill="#374151" text-anchor="middle">{{ bar.count }}</text>
              </g>
            </svg>
            <table class="report-table">
              <thead>
                <tr><th>Methode</th><th class="r">Gesamt</th><th class="r">Erfolg</th><th class="r">Fehler</th></tr>
              </thead>
              <tbody>
                <tr v-for="row in (payData?.by_method ?? [])" :key="row.method">
                  <td>{{ row.method }}</td>
                  <td class="r">{{ row.count }}</td>
                  <td class="r pos">{{ row.success_count }}</td>
                  <td class="r neg">{{ row.failed_count }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- Vendors tab -->
    <div v-if="activeTab === 'vendors'">
      <div v-if="vendorLoading" class="loading-state">Lade...</div>
      <template v-else>
        <div class="cards-row">
          <div class="card">
            <div class="card-title">Kosten nach Park</div>
            <table class="report-table">
              <thead><tr><th>Park</th><th class="r">Kosten</th></tr></thead>
              <tbody>
                <tr v-for="row in (vendorData?.by_park ?? [])" :key="row.park_name">
                  <td>{{ row.park_name }}</td>
                  <td class="r">{{ fmt(row.total) }}</td>
                </tr>
                <tr v-if="!(vendorData?.by_park ?? []).length"><td colspan="2" class="empty">Keine Daten</td></tr>
              </tbody>
            </table>
          </div>
          <div class="card">
            <div class="card-title">Kosten nach Fachgebiet</div>
            <table class="report-table">
              <thead><tr><th>Fachgebiet</th><th class="r">Kosten</th></tr></thead>
              <tbody>
                <tr v-for="row in (vendorData?.by_specialty ?? [])" :key="row.specialty">
                  <td>{{ row.specialty }}</td>
                  <td class="r">{{ fmt(row.total) }}</td>
                </tr>
                <tr v-if="!(vendorData?.by_specialty ?? []).length"><td colspan="2" class="empty">Keine Daten</td></tr>
              </tbody>
            </table>
          </div>
          <div class="card">
            <div class="card-title">Kosten nach Monat</div>
            <table class="report-table">
              <thead><tr><th>Monat</th><th class="r">Kosten</th></tr></thead>
              <tbody>
                <tr v-for="row in (vendorData?.by_month ?? [])" :key="row.month">
                  <td>{{ row.month }}</td>
                  <td class="r">{{ fmt(row.total) }}</td>
                </tr>
                <tr v-if="!(vendorData?.by_month ?? []).length"><td colspan="2" class="empty">Keine Daten</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.fin-reports { display: flex; flex-direction: column; gap: 1.25rem; }
.page-title { font-size: 1.375rem; font-weight: 700; color: #1e293b; margin: 0; }
.tab-bar { display: flex; border-bottom: 2px solid #e2e8f0; }
.tab-btn { padding: 0.625rem 1.25rem; background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-size: 0.875rem; color: #64748b; cursor: pointer; font-weight: 500; transition: color 0.15s, border-color 0.15s; }
.tab-btn:hover { color: #1e293b; }
.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
.filters-row { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.filter-ctrl { border: 1px solid #d1d5db; border-radius: 6px; padding: 0.4375rem 0.75rem; font-size: 0.875rem; color: #1e293b; background: #fff; outline: none; }
.filter-ctrl:focus { border-color: #3b82f6; }
.export-btn { margin-left: auto; padding: 0.4375rem 1rem; background: #f8fafc; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.875rem; color: #374151; cursor: pointer; }
.export-btn:hover { background: #f1f5f9; }
.metric-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; }
.metric-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; }
.metric-card.highlight { border-color: #bfdbfe; background: #eff6ff; }
.metric-card.highlight-red { border-color: #fecaca; background: #fef2f2; }
.metric-value { font-size: 1.75rem; font-weight: 700; color: #1e293b; line-height: 1.1; }
.metric-label { font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
.card-title { font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.875rem; }
.cards-row { display: flex; gap: 1.25rem; flex-wrap: wrap; }
.cards-row .card { flex: 1; min-width: 220px; }
.chart-row { display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap; }
.chart-empty { font-size: 0.875rem; color: #94a3b8; padding: 1rem 0; text-align: center; }
.report-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.report-table th { text-align: left; padding: 0.5rem 0.75rem; background: #f8fafc; font-weight: 600; color: #374151; border-bottom: 1px solid #e2e8f0; }
.report-table th.r { text-align: right; }
.report-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f1f5f9; color: #374151; }
.report-table td.r { text-align: right; font-weight: 500; color: #1e293b; }
.report-table td.pos { color: #16a34a; }
.report-table td.neg { color: #dc2626; }
.report-table td.bold { font-weight: 700; }
.report-table td.empty { text-align: center; color: #94a3b8; padding: 1rem; }
.level-badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.loading-state { padding: 2rem; text-align: center; color: #64748b; font-size: 0.875rem; }
</style>
