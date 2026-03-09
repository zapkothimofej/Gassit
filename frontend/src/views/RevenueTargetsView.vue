<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import api from '../api/axios'
import { fetchParks } from '../api/parks'

interface RevenueTarget {
  id: number | null
  month: number
  year: number
  target_amount: string
}

interface ActualData {
  actual: number
}

const parks = ref<Array<{ id: number; name: string }>>([])
const selectedParkId = ref<number | null>(null)
const year = ref(new Date().getFullYear())
const targets = ref<RevenueTarget[]>([])
const actuals = ref<Record<number, number>>({})
const loading = ref(false)
const savingMonth = ref<number | null>(null)
const toast = ref('')

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

onMounted(async () => {
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
  const firstPark = parks.value[0]
  if (firstPark) {
    selectedParkId.value = firstPark.id
    await loadAll()
  }
})

watch([selectedParkId, year], () => { if (selectedParkId.value) loadAll() })

async function loadAll() {
  if (!selectedParkId.value) return
  loading.value = true
  try {
    const targetsRes = await api.get<RevenueTarget[]>(
      '/parks/' + selectedParkId.value + '/revenue-targets',
      { params: { year: year.value } },
    )
    const raw = Array.isArray(targetsRes.data) ? targetsRes.data : []

    targets.value = MONTHS.map((_, i) => {
      const m = i + 1
      const existing = raw.find(t => t.month === m)
      return existing ?? { id: null, month: m, year: year.value, target_amount: '0' }
    })

    const acts: Record<number, number> = {}
    await Promise.allSettled(
      MONTHS.map(async (_, i) => {
        const m = i + 1
        try {
          const res = await api.get<ActualData>(
            '/parks/' + selectedParkId.value + '/revenue-targets/' + year.value + '/' + m + '/actual',
          )
          acts[m] = res.data.actual ?? 0
        } catch {
          acts[m] = 0
        }
      }),
    )
    actuals.value = acts
  } finally {
    loading.value = false
  }
}

async function saveTarget(monthIdx: number) {
  const t = targets.value[monthIdx]
  if (!selectedParkId.value || !t) return
  savingMonth.value = t.month
  try {
    await api.post('/parks/' + selectedParkId.value + '/revenue-targets', {
      year: year.value,
      month: t.month,
      target_amount: Number(t.target_amount),
    })
    showToast('Target saved for ' + MONTHS[monthIdx] + '.')
  } finally {
    savingMonth.value = null
  }
}

function pct(monthIdx: number) {
  const t = targets.value[monthIdx]
  if (!t) return null
  const target = parseFloat(t.target_amount) || 0
  const actual = actuals.value[monthIdx + 1] ?? 0
  if (target === 0) return null
  return Math.round((actual / target) * 100)
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Revenue Targets</h2>
      <div class="header-right">
        <select v-if="parks.length > 1" v-model="selectedParkId" class="park-sel">
          <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <div class="year-nav">
          <button class="nav-btn" @click="year--">‹</button>
          <span class="year-label">{{ year }}</span>
          <button class="nav-btn" @click="year++">›</button>
        </div>
      </div>
    </div>

    <div v-if="toast" class="toast">{{ toast }}</div>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="months-grid">
      <div v-for="(monthName, i) in MONTHS" :key="monthName" class="month-card">
        <div class="month-name">{{ monthName }} {{ year }}</div>
        <div class="target-field">
          <label class="field-label">Target (€)</label>
          <input
            v-model="targets[i]!.target_amount"
            type="number"
            min="0"
            step="100"
            class="target-input"
            @blur="saveTarget(i)"
          />
          <div v-if="savingMonth === targets[i]!.month" class="saving-text">Saving...</div>
        </div>
        <div class="actual-row">
          <div class="actual-label">Actual</div>
          <div class="actual-value">{{ (actuals[i + 1] ?? 0).toFixed(0) }} €</div>
        </div>
        <div v-if="pct(i) !== null" class="pct-bar">
          <div class="pct-fill" :style="{ width: Math.min(pct(i)!, 100) + '%', background: (pct(i) ?? 0) >= 100 ? '#22c55e' : (pct(i) ?? 0) >= 70 ? '#f59e0b' : '#ef4444' }"></div>
          <div class="pct-text">{{ pct(i) }}%</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.header-right { display: flex; gap: 0.75rem; align-items: center; }
.park-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.year-nav { display: flex; align-items: center; gap: 0.5rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.25rem 0.5rem; }
.nav-btn { border: none; background: none; cursor: pointer; font-size: 1.25rem; color: #374151; padding: 0 0.25rem; }
.nav-btn:hover { color: #3b82f6; }
.year-label { font-weight: 600; color: #1e293b; min-width: 48px; text-align: center; }
.toast { background: #22c55e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 6px; text-align: center; }
.loading { color: #64748b; font-size: 0.875rem; padding: 1rem 0; }

.months-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; }
.month-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; display: flex; flex-direction: column; gap: 0.625rem; }
.month-name { font-size: 0.875rem; font-weight: 600; color: #374151; }
.target-field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.75rem; color: #64748b; }
.target-input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.35rem 0.5rem; font-size: 0.875rem; width: 100%; box-sizing: border-box; }
.target-input:focus { outline: none; border-color: #3b82f6; }
.saving-text { font-size: 0.7rem; color: #64748b; }
.actual-row { display: flex; justify-content: space-between; font-size: 0.8rem; }
.actual-label { color: #64748b; }
.actual-value { font-weight: 600; color: #1e293b; }
.pct-bar { position: relative; background: #f1f5f9; border-radius: 4px; height: 6px; overflow: hidden; }
.pct-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.pct-text { font-size: 0.7rem; color: #64748b; margin-top: 0.2rem; text-align: right; }
</style>
