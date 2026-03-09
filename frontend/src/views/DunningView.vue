<script setup lang="ts">
import { ref, computed, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppTable from '../components/AppTable.vue'
import AppButton from '../components/AppButton.vue'
import AppModal from '../components/AppModal.vue'
import StatusBadge from '../components/StatusBadge.vue'
import api from '../api/axios'
import { fetchParks } from '../api/parks'

const router = useRouter()

interface Debtor {
  customer: {
    id: number
    first_name: string
    last_name: string
    company_name: string | null
    type?: string
    status: string
    dunning_paused_until: string | null
  }
  total_owed: number
  dunning_level: number
  days_overdue: number
  invoice_count: number
}

const debtors = ref<Debtor[]>([])
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])

const filters = reactive({
  park_id: null as number | null,
  dunning_level: '' as '' | '0' | '1' | '2' | '3',
})

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {}
    if (filters.park_id) params.park_id = filters.park_id
    if (filters.dunning_level !== '') params.dunning_level = filters.dunning_level
    const res = await api.get<Debtor[]>('/debtors', { params })
    debtors.value = res.data ?? []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

const metrics = computed(() => ({
  total: debtors.value.length,
  totalOwed: debtors.value.reduce((s, d) => s + d.total_owed, 0),
  byLevel: [0, 1, 2, 3].map(lvl => debtors.value.filter(d => d.dunning_level === lvl).length),
}))

function customerName(d: Debtor) {
  const c = d.customer
  return c.company_name ? c.company_name : c.first_name + ' ' + c.last_name
}

const columns = [
  { key: 'name', label: 'Customer', sortable: false },
  { key: 'total_owed', label: 'Total Owed', sortable: false },
  { key: 'dunning_level', label: 'Level', sortable: false },
  { key: 'days_overdue', label: 'Days Overdue', sortable: false },
  { key: 'paused', label: 'Paused', sortable: false },
  { key: 'actions', label: 'Actions', sortable: false },
]

// --- Pause ---
const showPauseModal = ref(false)
const pauseTarget = ref<Debtor | null>(null)
const pausing = ref(false)
function openPause(d: Debtor) { pauseTarget.value = d; showPauseModal.value = true }
async function doPause() {
  if (!pauseTarget.value) return
  pausing.value = true
  try {
    await api.post('/debtors/' + pauseTarget.value.customer.id + '/pause')
    showPauseModal.value = false
    await load()
  } finally {
    pausing.value = false
  }
}

// --- Escalate ---
const showEscalateModal = ref(false)
const escalateTarget = ref<Debtor | null>(null)
const escalating = ref(false)
function openEscalate(d: Debtor) { escalateTarget.value = d; showEscalateModal.value = true }
const nextLevel = computed(() => escalateTarget.value ? Math.min(3, escalateTarget.value.dunning_level + 1) : 1)
const nextFee = computed(() => [5, 10, 30][nextLevel.value - 1] ?? 30)
async function doEscalate() {
  if (!escalateTarget.value) return
  escalating.value = true
  try {
    await api.post('/debtors/' + escalateTarget.value.customer.id + '/escalate')
    showEscalateModal.value = false
    await load()
  } finally {
    escalating.value = false
  }
}

// --- Resolve ---
const showResolveModal = ref(false)
const resolveTarget = ref<Debtor | null>(null)
const resolving = ref(false)
const resolveForm = reactive({ notes: '', reference: '' })
function openResolve(d: Debtor) {
  resolveTarget.value = d
  resolveForm.notes = ''
  resolveForm.reference = ''
  showResolveModal.value = true
}
async function doResolve() {
  if (!resolveTarget.value) return
  resolving.value = true
  try {
    await api.post('/debtors/' + resolveTarget.value.customer.id + '/resolve', {
      notes: resolveForm.notes,
      reference: resolveForm.reference,
    })
    showResolveModal.value = false
    await load()
  } finally {
    resolving.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Debtors & Dunning</h2>
    </div>

    <!-- Summary Metrics -->
    <div class="metrics-row">
      <div class="metric-card">
        <div class="metric-value">{{ metrics.total }}</div>
        <div class="metric-label">Total Debtors</div>
      </div>
      <div class="metric-card">
        <div class="metric-value">{{ metrics.totalOwed.toFixed(2) }} €</div>
        <div class="metric-label">Total Owed</div>
      </div>
      <div v-for="lvl in [1,2,3]" :key="lvl" class="metric-card">
        <div class="metric-value level-badge" :class="'level-' + lvl">{{ metrics.byLevel[lvl] }}</div>
        <div class="metric-label">Level {{ lvl }}</div>
      </div>
    </div>

    <div class="filters">
      <select v-model="filters.park_id" class="filter-sel" @change="load()">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.dunning_level" class="filter-sel" @change="load()">
        <option value="">All Levels</option>
        <option value="0">Level 0 (No dunning)</option>
        <option value="1">Level 1</option>
        <option value="2">Level 2</option>
        <option value="3">Level 3</option>
      </select>
    </div>

    <AppTable
      :columns="columns"
      :rows="(debtors as unknown as Record<string, unknown>[])"
      @row-click="(row) => router.push('/customers/' + (row as unknown as Debtor).customer.id)"
    >
      <template #cell-name="{ row }">
        <span class="customer-name">{{ customerName(row as unknown as Debtor) }}</span>
      </template>
      <template #cell-total_owed="{ row }">
        <span class="amount-owed">{{ (row as unknown as Debtor).total_owed.toFixed(2) }} €</span>
      </template>
      <template #cell-dunning_level="{ row }">
        <span class="level-badge" :class="'level-' + (row as unknown as Debtor).dunning_level">
          Level {{ (row as unknown as Debtor).dunning_level }}
        </span>
      </template>
      <template #cell-days_overdue="{ row }">
        <span :class="{ 'overdue-high': (row as unknown as Debtor).days_overdue > 30 }">
          {{ (row as unknown as Debtor).days_overdue }}d
        </span>
      </template>
      <template #cell-paused="{ row }">
        <StatusBadge
          v-if="(row as unknown as Debtor).customer.dunning_paused_until"
          status="paused"
        />
        <span v-else>–</span>
      </template>
      <template #cell-actions="{ row }">
        <div class="row-actions" @click.stop>
          <button
            class="btn-action pause-btn"
            :disabled="!!(row as unknown as Debtor).customer.dunning_paused_until"
            @click="openPause(row as unknown as Debtor)"
          >Pause</button>
          <button
            class="btn-action escalate-btn"
            :disabled="(row as unknown as Debtor).dunning_level >= 3"
            @click="openEscalate(row as unknown as Debtor)"
          >Escalate</button>
          <button
            class="btn-action resolve-btn"
            @click="openResolve(row as unknown as Debtor)"
          >Resolve</button>
        </div>
      </template>
      <template #empty>No debtors found.</template>
    </AppTable>

    <!-- Pause Modal -->
    <AppModal v-model="showPauseModal" title="Pause Dunning">
      <p>Pause dunning for <strong>{{ pauseTarget ? customerName(pauseTarget) : '' }}</strong> for 30 days?</p>
      <template #footer>
        <AppButton variant="secondary" @click="showPauseModal = false">Cancel</AppButton>
        <AppButton :loading="pausing" @click="doPause">Pause</AppButton>
      </template>
    </AppModal>

    <!-- Escalate Modal -->
    <AppModal v-model="showEscalateModal" title="Escalate Dunning">
      <p>
        Escalate <strong>{{ escalateTarget ? customerName(escalateTarget) : '' }}</strong> to
        <strong>Level {{ nextLevel }}</strong>?
      </p>
      <p class="fee-note">A dunning fee of <strong>{{ nextFee }} €</strong> will be added.</p>
      <template #footer>
        <AppButton variant="secondary" @click="showEscalateModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="escalating" @click="doEscalate">Escalate</AppButton>
      </template>
    </AppModal>

    <!-- Resolve Modal -->
    <AppModal v-model="showResolveModal" title="Resolve Dunning">
      <p>
        Mark all overdue invoices for <strong>{{ resolveTarget ? customerName(resolveTarget) : '' }}</strong> as paid and resolve dunning?
      </p>
      <div class="resolve-fields">
        <label class="field-label">
          Reference <span class="required">*</span>
          <input v-model="resolveForm.reference" class="field-input" placeholder="e.g. bank transfer ref #" required />
        </label>
        <label class="field-label">
          Notes <span class="required">*</span>
          <textarea v-model="resolveForm.notes" class="field-input" rows="3" placeholder="Reason for manual resolution" required></textarea>
        </label>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showResolveModal = false">Cancel</AppButton>
        <AppButton
          :loading="resolving"
          :disabled="!resolveForm.notes.trim() || !resolveForm.reference.trim()"
          @click="doResolve"
        >Resolve</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }

.metrics-row { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; }
.metric-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; text-align: center; }
.metric-value { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
.metric-label { font-size: 0.8rem; color: #64748b; margin-top: 0.25rem; }

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }

.customer-name { font-weight: 500; }
.amount-owed { color: #dc2626; font-weight: 600; }
.overdue-high { color: #dc2626; font-weight: 600; }

.level-badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
.level-0 { background: #f1f5f9; color: #64748b; }
.level-1 { background: #fef3c7; color: #d97706; }
.level-2 { background: #fed7aa; color: #ea580c; }
.level-3 { background: #fee2e2; color: #dc2626; }

.row-actions { display: flex; gap: 0.375rem; }
.btn-action { border: 1px solid #cbd5e1; border-radius: 4px; padding: 0.2rem 0.6rem; font-size: 0.8rem; cursor: pointer; background: #fff; transition: all 0.1s; }
.btn-action:disabled { opacity: 0.4; cursor: not-allowed; }
.pause-btn:not(:disabled):hover { border-color: #64748b; background: #f1f5f9; }
.escalate-btn:not(:disabled):hover { border-color: #dc2626; color: #dc2626; background: #fef2f2; }
.resolve-btn:not(:disabled):hover { border-color: #16a34a; color: #16a34a; background: #f0fdf4; }

.fee-note { font-size: 0.875rem; color: #64748b; margin: 0; }

.resolve-fields { display: flex; flex-direction: column; gap: 0.75rem; margin-top: 0.75rem; }
.field-label { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.875rem; font-weight: 500; color: #374151; }
.field-input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.6rem; font-size: 0.875rem; width: 100%; box-sizing: border-box; }
.required { color: #dc2626; }
</style>
