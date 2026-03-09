<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppTable from '../components/AppTable.vue'
import AppPagination from '../components/AppPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import AppModal from '../components/AppModal.vue'
import AppButton from '../components/AppButton.vue'
import FormSelect from '../components/FormSelect.vue'
import FormDatePicker from '../components/FormDatePicker.vue'
import FormTextarea from '../components/FormTextarea.vue'
import {
  fetchApplications, createApplication, searchCustomers,
  type Application,
} from '../api/applications'
import { fetchParks, fetchUnitTypes } from '../api/parks'
import api from '../api/axios'

const router = useRouter()

// Filters
const filters = reactive({
  park_id: null as number | null,
  status: [] as string[],
  assigned_to: null as number | null,
  from: null as string | null,
  to: null as string | null,
  search: '',
  page: 1,
})

const STATUS_OPTIONS = [
  { value: 'new', label: 'New' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'waiting', label: 'Waiting' },
  { value: 'completed', label: 'Completed' },
  { value: 'declined', label: 'Declined' },
]

const SOURCE_OPTIONS = [
  { value: 'online', label: 'Online' },
  { value: 'phone', label: 'Phone' },
  { value: 'walk_in', label: 'Walk-in' },
  { value: 'referral', label: 'Referral' },
]

// Data
const applications = ref<Application[]>([])
const totalPages = ref(1)
const loading = ref(false)

const parks = ref<Array<{ id: number; name: string }>>([])
const unitTypes = ref<Array<{ id: number; name: string; park_id: number }>>([])
const users = ref<Array<{ id: number; name: string }>>([])

async function loadApplications() {
  loading.value = true
  try {
    const res = await fetchApplications({
      park_id: filters.park_id,
      status: filters.status.length ? filters.status : undefined,
      assigned_to: filters.assigned_to,
      from: filters.from || undefined,
      to: filters.to || undefined,
      search: filters.search || undefined,
      page: filters.page,
      per_page: 20,
    })
    applications.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

watch(
  () => [filters.park_id, filters.status, filters.assigned_to, filters.from, filters.to, filters.search],
  () => { filters.page = 1; loadApplications() },
)

onMounted(async () => {
  loadApplications()
  const [pr, us] = await Promise.allSettled([fetchParks(), api.get('/admin/users')])
  if (pr.status === 'fulfilled') parks.value = pr.value.data.data ?? []
  if (us.status === 'fulfilled') users.value = us.value.data.data ?? []
})

const columns = [
  { key: 'id', label: 'ID', sortable: false },
  { key: 'customer', label: 'Customer', sortable: false },
  { key: 'unit_type', label: 'Unit Type', sortable: false },
  { key: 'park', label: 'Park', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'assigned_to', label: 'Assigned To', sortable: false },
  { key: 'created_at', label: 'Created', sortable: true },
]

// Create Modal
const showModal = ref(false)
const creating = ref(false)
const form = reactive({
  customer_id: null as number | null,
  unit_type_id: null as number | null,
  park_id: null as number | null,
  desired_start_date: null as string | null,
  notes: null as string | null,
  source: 'online',
})
const customerSearch = ref('')
const customerResults = ref<Array<{ id: number; first_name: string; last_name: string; email: string }>>([])
const selectedCustomerLabel = ref('')

let searchTimeout: ReturnType<typeof setTimeout>
watch(customerSearch, (q) => {
  clearTimeout(searchTimeout)
  if (!q) { customerResults.value = []; return }
  searchTimeout = setTimeout(async () => {
    const res = await searchCustomers(q)
    customerResults.value = res.data.data ?? []
  }, 300)
})

watch(() => form.park_id, async (pid) => {
  unitTypes.value = []
  form.unit_type_id = null
  if (!pid) return
  const res = await fetchUnitTypes(pid)
  unitTypes.value = res.data.data ?? []
})

function selectCustomer(c: { id: number; first_name: string; last_name: string; email: string }) {
  form.customer_id = c.id
  selectedCustomerLabel.value = c.first_name + ' ' + c.last_name + ' (' + c.email + ')'
  customerResults.value = []
  customerSearch.value = ''
}

async function submitCreate() {
  if (!form.customer_id || !form.unit_type_id || !form.park_id) return
  creating.value = true
  try {
    await createApplication({
      customer_id: form.customer_id,
      unit_type_id: form.unit_type_id,
      park_id: form.park_id,
      desired_start_date: form.desired_start_date || undefined,
      notes: form.notes || undefined,
      source: form.source,
    })
    showModal.value = false
    loadApplications()
  } finally {
    creating.value = false
  }
}

function formatDate(d: string) {
  return d ? d.slice(0, 10) : '-'
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Applications</h2>
      <AppButton @click="showModal = true">+ New Application</AppButton>
    </div>

    <!-- Filters -->
    <div class="filters">
      <input v-model="filters.search" class="search-input" placeholder="Search customer..." />
      <select v-model="filters.park_id" class="filter-select">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.assigned_to" class="filter-select">
        <option :value="null">All Assignees</option>
        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <div class="multi-select">
        <span class="multi-label">Status:</span>
        <label v-for="opt in STATUS_OPTIONS" :key="opt.value" class="check-label">
          <input type="checkbox" :value="opt.value" v-model="filters.status" />
          {{ opt.label }}
        </label>
      </div>
      <input v-model="filters.from" type="date" class="filter-select" />
      <input v-model="filters.to" type="date" class="filter-select" />
    </div>

    <!-- Table -->
    <AppTable
      :columns="columns"
      :rows="(applications as unknown as Record<string, unknown>[])"
      @row-click="(row) => router.push('/applications/' + (row as unknown as Application).id)"
    >
      <template #cell-customer="{ row }">
        <span>{{ (row as unknown as Application).customer?.first_name }} {{ (row as unknown as Application).customer?.last_name }}</span>
      </template>
      <template #cell-unit_type="{ row }">
        {{ (row as unknown as Application).unit_type?.name ?? '-' }}
      </template>
      <template #cell-park="{ row }">
        {{ (row as unknown as Application).park?.name ?? '-' }}
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="(row as unknown as Application).status" />
      </template>
      <template #cell-assigned_to="{ row }">
        {{ (row as unknown as Application).assigned_to?.name ?? '-' }}
      </template>
      <template #cell-created_at="{ row }">
        {{ formatDate((row as unknown as Application).created_at) }}
      </template>
      <template #empty>No applications found.</template>
    </AppTable>

    <AppPagination
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p) => { filters.page = p; loadApplications() }"
    />

    <!-- Create Modal -->
    <AppModal v-model="showModal" title="New Application">
      <div class="modal-form">
        <!-- Customer search -->
        <div class="form-field">
          <label class="field-label">Customer *</label>
          <input
            v-model="customerSearch"
            class="form-input"
            placeholder="Type to search..."
          />
          <div v-if="selectedCustomerLabel" class="selected-customer">
            Selected: {{ selectedCustomerLabel }}
            <button @click="form.customer_id = null; selectedCustomerLabel = ''" class="clear-btn">x</button>
          </div>
          <div v-if="customerResults.length" class="dropdown">
            <div
              v-for="c in customerResults"
              :key="c.id"
              class="dropdown-item"
              @click="selectCustomer(c)"
            >
              {{ c.first_name }} {{ c.last_name }} ({{ c.email }})
            </div>
          </div>
        </div>

        <FormSelect
          label="Park *"
          :model-value="form.park_id"
          :options="parks.map(p => ({ value: p.id, label: p.name }))"
          placeholder="Select park..."
          @update:model-value="form.park_id = Number($event)"
        />

        <FormSelect
          label="Unit Type *"
          :model-value="form.unit_type_id"
          :options="unitTypes.map(u => ({ value: u.id, label: u.name }))"
          placeholder="Select unit type..."
          :disabled="!form.park_id"
          @update:model-value="form.unit_type_id = Number($event)"
        />

        <FormSelect
          label="Source"
          :model-value="form.source"
          :options="SOURCE_OPTIONS"
          @update:model-value="form.source = $event"
        />

        <FormDatePicker
          label="Desired Start Date"
          :model-value="form.desired_start_date"
          @update:model-value="form.desired_start_date = $event"
        />

        <FormTextarea
          label="Notes"
          :model-value="form.notes"
          placeholder="Additional notes..."
          @update:model-value="form.notes = $event"
        />
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="creating" @click="submitCreate">Create</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }

.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.875rem 1rem;
}

.search-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.4rem 0.75rem;
  font-size: 0.875rem;
  min-width: 200px;
}

.filter-select {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.4rem 0.75rem;
  font-size: 0.875rem;
}

.multi-select {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.multi-label { font-size: 0.8rem; color: #64748b; font-weight: 500; }

.check-label {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.8rem;
  cursor: pointer;
}

.modal-form { display: flex; flex-direction: column; gap: 1rem; min-width: 400px; }

.form-field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.form-input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.875rem; }

.selected-customer {
  font-size: 0.8rem;
  color: #374151;
  background: #f0fdf4;
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.clear-btn { background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 0.9rem; }

.dropdown {
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  max-height: 200px;
  overflow-y: auto;
}

.dropdown-item {
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  cursor: pointer;
}

.dropdown-item:hover { background: #f8fafc; }
</style>
