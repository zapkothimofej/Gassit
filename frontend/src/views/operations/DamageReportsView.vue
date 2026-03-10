<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppTable from '../../components/AppTable.vue'
import AppPagination from '../../components/AppPagination.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import AppModal from '../../components/AppModal.vue'
import AppButton from '../../components/AppButton.vue'
import FormInput from '../../components/FormInput.vue'
import FormTextarea from '../../components/FormTextarea.vue'
import api from '../../api/axios'
import { fetchParks } from '../../api/parks'

const router = useRouter()

interface DamageReport {
  id: number
  status: string
  description: string
  estimated_cost: string | null
  created_at: string
  unit: { id: number; unit_number: string; park_id: number }
  reported_by: { id: number; name: string }
  assigned_vendor: { id: number; name: string } | null
}

const reports = ref<DamageReport[]>([])
const totalPages = ref(1)
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])

const filters = reactive({
  park_id: null as number | null,
  status: '',
  page: 1,
})

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'reported', label: 'Reported' },
  { value: 'in_assessment', label: 'In Assessment' },
  { value: 'repair_ordered', label: 'Repair Ordered' },
  { value: 'in_repair', label: 'In Repair' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'closed', label: 'Closed' },
]

async function load() {
  loading.value = true
  try {
    const res = await api.get<{ data: DamageReport[]; last_page: number }>('/damage-reports', {
      params: {
        park_id: filters.park_id || undefined,
        status: filters.status || undefined,
        page: filters.page,
        per_page: 20,
      },
    })
    reports.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

watch(
  () => [filters.park_id, filters.status],
  () => { filters.page = 1; load() },
)

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

const columns = [
  { key: 'id', label: 'Report ID', sortable: false },
  { key: 'unit', label: 'Unit', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'reported_by', label: 'Reported By', sortable: false },
  { key: 'estimated_cost', label: 'Est. Cost', sortable: false },
  { key: 'assigned_vendor', label: 'Vendor', sortable: false },
  { key: 'created_at', label: 'Created', sortable: false },
]

// Create Modal
const showCreateModal = ref(false)
const creating = ref(false)
const cForm = reactive({
  unit_id: '' as string | number,
  description: '' as string | null,
  estimated_cost: '',
})

async function submitCreate() {
  creating.value = true
  try {
    const res = await api.post<DamageReport>('/damage-reports', {
      unit_id: Number(cForm.unit_id),
      description: cForm.description,
      estimated_cost: cForm.estimated_cost ? Number(cForm.estimated_cost) : null,
    })
    showCreateModal.value = false
    router.push('/damage-reports/' + res.data.id)
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Damage Reports</h2>
      <AppButton @click="showCreateModal = true">+ New Report</AppButton>
    </div>

    <div class="filters">
      <select v-model="filters.park_id" class="filter-sel">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.status" class="filter-sel">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <AppTable
      :columns="columns"
      :rows="(reports as unknown as Record<string, unknown>[])"
      @row-click="(row) => router.push('/damage-reports/' + (row as unknown as DamageReport).id)"
    >
      <template #cell-id="{ row }">
        <span class="mono">#{{ (row as unknown as DamageReport).id }}</span>
      </template>
      <template #cell-unit="{ row }">
        {{ (row as unknown as DamageReport).unit?.unit_number ?? '–' }}
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="(row as unknown as DamageReport).status" />
      </template>
      <template #cell-reported_by="{ row }">
        {{ (row as unknown as DamageReport).reported_by?.name ?? '–' }}
      </template>
      <template #cell-estimated_cost="{ row }">
        {{ (row as unknown as DamageReport).estimated_cost ? (row as unknown as DamageReport).estimated_cost + ' €' : '–' }}
      </template>
      <template #cell-assigned_vendor="{ row }">
        {{ (row as unknown as DamageReport).assigned_vendor?.name ?? '–' }}
      </template>
      <template #cell-created_at="{ row }">
        {{ (row as unknown as DamageReport).created_at?.slice(0, 10) }}
      </template>
      <template #empty>No damage reports found.</template>
    </AppTable>

    <AppPagination
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p) => { filters.page = p; load() }"
    />

    <!-- Create Modal -->
    <AppModal v-model="showCreateModal" title="New Damage Report">
      <div class="modal-form">
        <div>
          <label class="field-label">Unit ID *</label>
          <input v-model="cForm.unit_id" type="number" class="text-input" placeholder="Enter unit ID..." />
        </div>
        <FormTextarea label="Description *" :model-value="cForm.description" @update:model-value="cForm.description = $event" :rows="4" />
        <FormInput label="Estimated Cost (€)" type="number" :model-value="cForm.estimated_cost" @update:model-value="cForm.estimated_cost = $event" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showCreateModal = false">Cancel</AppButton>
        <AppButton :loading="creating" :disabled="!cForm.unit_id || !cForm.description" @click="submitCreate">Create</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.mono { font-family: monospace; font-size: 0.85rem; }
.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 420px; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem; }
.text-input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; width: 100%; box-sizing: border-box; }
</style>
