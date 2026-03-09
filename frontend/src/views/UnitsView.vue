<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '../components/AppButton.vue'
import AppPagination from '../components/AppPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import AppModal from '../components/AppModal.vue'
import FormInput from '../components/FormInput.vue'
import FormSelect from '../components/FormSelect.vue'
import { fetchUnits, createUnit, updateUnitStatus, type Unit } from '../api/units'
import { fetchParks, fetchUnitTypes, type Park, type UnitType } from '../api/parks'

const router = useRouter()

const VIEW_KEY = 'units_view'
const viewMode = ref<'card' | 'table'>(
  (localStorage.getItem(VIEW_KEY) as 'card' | 'table') ?? 'card',
)

function setView(mode: 'card' | 'table') {
  viewMode.value = mode
  localStorage.setItem(VIEW_KEY, mode)
}

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'free', label: 'Free' },
  { value: 'reserved', label: 'Reserved' },
  { value: 'active', label: 'Active' },
  { value: 'in_repair', label: 'In Repair' },
]

const BULK_STATUS_OPTIONS = [
  { value: 'free', label: 'Free' },
  { value: 'reserved', label: 'Reserved' },
  { value: 'active', label: 'Active' },
  { value: 'in_repair', label: 'In Repair' },
]

// Filter state
const parks = ref<Park[]>([])
const unitTypes = ref<UnitType[]>([])
const filters = reactive({
  park_id: null as number | null,
  unit_type_id: null as number | null,
  status: '',
  page: 1,
})

// Data
const units = ref<Unit[]>([])
const totalPages = ref(1)
const loading = ref(false)

async function loadUnits() {
  if (!filters.park_id) {
    units.value = []
    totalPages.value = 1
    return
  }
  loading.value = true
  try {
    const res = await fetchUnits(filters.park_id, {
      unit_type_id: filters.unit_type_id ?? undefined,
      status: filters.status || undefined,
      page: filters.page,
      per_page: 20,
    })
    units.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

watch(
  () => [filters.park_id, filters.unit_type_id, filters.status],
  () => { filters.page = 1; loadUnits() },
)

watch(() => filters.park_id, async (pid) => {
  unitTypes.value = []
  filters.unit_type_id = null
  if (!pid) return
  const res = await fetchUnitTypes(pid)
  unitTypes.value = res.data.data ?? []
})

onMounted(async () => {
  const res = await fetchParks()
  parks.value = res.data.data ?? []
  const firstPark = parks.value[0]
  if (firstPark) {
    filters.park_id = firstPark.id
  }
})

// Table checkboxes
const selectedIds = ref<Set<number>>(new Set())
const allSelected = computed(() =>
  units.value.length > 0 && units.value.every((u) => selectedIds.value.has(u.id)),
)

function toggleAll() {
  if (allSelected.value) {
    selectedIds.value = new Set()
  } else {
    selectedIds.value = new Set(units.value.map((u) => u.id))
  }
}

function toggleRow(id: number) {
  const s = new Set(selectedIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedIds.value = s
}

// Bulk status
const bulkStatus = ref('')
const applyingBulk = ref(false)

async function applyBulkStatus() {
  if (!bulkStatus.value || selectedIds.value.size === 0) return
  applyingBulk.value = true
  try {
    await Promise.all(
      [...selectedIds.value].map((id) => updateUnitStatus(id, bulkStatus.value)),
    )
    selectedIds.value = new Set()
    bulkStatus.value = ''
    loadUnits()
  } finally {
    applyingBulk.value = false
  }
}

// Create modal
const showModal = ref(false)
const creating = ref(false)
const form = reactive({
  unit_number: '',
  unit_type_id: null as number | null,
  size_m2: null as number | null,
  rent_amount: null as number | null,
  building: '',
  floor: null as number | null,
})

function openModal() {
  form.unit_number = ''
  form.unit_type_id = null
  form.size_m2 = null
  form.rent_amount = null
  form.building = ''
  form.floor = null
  showModal.value = true
}

async function submitCreate() {
  if (!filters.park_id || !form.unit_number || !form.unit_type_id) return
  creating.value = true
  try {
    await createUnit(filters.park_id, {
      unit_number: form.unit_number,
      unit_type_id: form.unit_type_id,
      size_m2: form.size_m2,
      rent_amount: form.rent_amount,
      building: form.building || null,
      floor: form.floor,
    })
    showModal.value = false
    loadUnits()
  } finally {
    creating.value = false
  }
}

function tenantName(unit: Unit) {
  if (!unit.current_tenant) return null
  return unit.current_tenant.first_name + ' ' + unit.current_tenant.last_name
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Units</h2>
      <div class="header-actions">
        <div class="view-toggle">
          <button :class="{ active: viewMode === 'card' }" @click="setView('card')" title="Card view">
            ⊞
          </button>
          <button :class="{ active: viewMode === 'table' }" @click="setView('table')" title="Table view">
            ☰
          </button>
        </div>
        <AppButton @click="openModal">+ New Unit</AppButton>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters">
      <select v-model="filters.park_id" class="filter-select">
        <option :value="null">Select Park</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.unit_type_id" class="filter-select" :disabled="!filters.park_id">
        <option :value="null">All Types</option>
        <option v-for="t in unitTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
      <select v-model="filters.status" class="filter-select">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>

      <!-- Bulk actions (table only) -->
      <template v-if="viewMode === 'table' && selectedIds.size > 0">
        <span class="bulk-label">{{ selectedIds.size }} selected:</span>
        <select v-model="bulkStatus" class="filter-select">
          <option value="">Change status...</option>
          <option v-for="opt in BULK_STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <AppButton size="sm" :disabled="!bulkStatus" :loading="applyingBulk" @click="applyBulkStatus">
          Apply
        </AppButton>
      </template>
    </div>

    <div v-if="!filters.park_id" class="empty-hint">Select a park to view units.</div>

    <!-- Card View -->
    <div v-else-if="viewMode === 'card'" class="card-grid">
      <div
        v-for="unit in units"
        :key="unit.id"
        class="unit-card"
        @click="router.push('/units/' + unit.id)"
      >
        <div class="card-header">
          <span class="unit-number">{{ unit.unit_number }}</span>
          <StatusBadge :status="unit.status" />
        </div>
        <div class="card-body">
          <span class="card-meta">{{ unit.unit_type?.name ?? '—' }}</span>
          <span v-if="unit.size_m2" class="card-meta">{{ unit.size_m2 }} m²</span>
          <span v-if="tenantName(unit)" class="tenant-name">{{ tenantName(unit) }}</span>
        </div>
      </div>
      <div v-if="!loading && units.length === 0" class="empty-hint">No units found.</div>
    </div>

    <!-- Table View -->
    <div v-else class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th><input type="checkbox" :checked="allSelected" @change="toggleAll" /></th>
            <th>Unit #</th>
            <th>Type</th>
            <th>Building</th>
            <th>Floor</th>
            <th>Size (m²)</th>
            <th>Rent</th>
            <th>Status</th>
            <th>Tenant</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="unit in units"
            :key="unit.id"
            class="data-row"
            @click="router.push('/units/' + unit.id)"
          >
            <td @click.stop>
              <input
                type="checkbox"
                :checked="selectedIds.has(unit.id)"
                @change="toggleRow(unit.id)"
              />
            </td>
            <td class="unit-number-cell">{{ unit.unit_number }}</td>
            <td>{{ unit.unit_type?.name ?? '—' }}</td>
            <td>{{ unit.building ?? '—' }}</td>
            <td>{{ unit.floor ?? '—' }}</td>
            <td>{{ unit.size_m2 ?? '—' }}</td>
            <td>{{ unit.rent_amount }}</td>
            <td><StatusBadge :status="unit.status" /></td>
            <td>{{ tenantName(unit) ?? '—' }}</td>
          </tr>
          <tr v-if="!loading && units.length === 0">
            <td colspan="9" class="empty-cell">No units found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppPagination
      v-if="filters.park_id && totalPages > 1"
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p) => { filters.page = p; loadUnits() }"
    />

    <!-- Create Modal -->
    <AppModal v-model="showModal" title="New Unit">
      <div class="modal-form">
        <FormInput
          label="Unit Number"
          :model-value="form.unit_number"
          placeholder="e.g. A-101"
          required
          @update:model-value="form.unit_number = $event"
        />
        <FormSelect
          label="Unit Type"
          :model-value="form.unit_type_id"
          :options="unitTypes.map(t => ({ value: t.id, label: t.name }))"
          placeholder="Select type..."
          required
          :disabled="!filters.park_id"
          @update:model-value="form.unit_type_id = Number($event)"
        />
        <FormInput
          label="Size (m²)"
          type="number"
          :model-value="form.size_m2"
          placeholder="e.g. 12.5"
          @update:model-value="form.size_m2 = $event ? Number($event) : null"
        />
        <FormInput
          label="Rent Amount"
          type="number"
          :model-value="form.rent_amount"
          placeholder="e.g. 150.00"
          @update:model-value="form.rent_amount = $event ? Number($event) : null"
        />
        <FormInput
          label="Building"
          :model-value="form.building"
          placeholder="e.g. Block A"
          @update:model-value="form.building = $event"
        />
        <FormInput
          label="Floor"
          type="number"
          :model-value="form.floor"
          placeholder="e.g. 1"
          @update:model-value="form.floor = $event ? Number($event) : null"
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

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.page-header h2 { margin: 0; }

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.view-toggle {
  display: flex;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  overflow: hidden;
}

.view-toggle button {
  background: #fff;
  border: none;
  padding: 0.35rem 0.65rem;
  cursor: pointer;
  font-size: 1rem;
  color: #64748b;
  transition: background 0.1s;
}

.view-toggle button.active {
  background: #3b82f6;
  color: #fff;
}

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

.filter-select {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.4rem 0.75rem;
  font-size: 0.875rem;
  background: #fff;
}

.bulk-label {
  font-size: 0.875rem;
  color: #374151;
  font-weight: 500;
}

.empty-hint {
  text-align: center;
  color: #94a3b8;
  padding: 3rem 0;
  font-size: 0.9rem;
}

/* Card grid */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
}

.unit-card {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1rem;
  cursor: pointer;
  transition: box-shadow 0.15s;
}

.unit-card:hover {
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.5rem;
}

.unit-number {
  font-weight: 700;
  font-size: 1rem;
  color: #1e293b;
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.card-meta {
  font-size: 0.8rem;
  color: #64748b;
}

.tenant-name {
  font-size: 0.8rem;
  color: #374151;
  font-weight: 500;
  margin-top: 0.25rem;
}

/* Table */
.table-wrapper {
  overflow-x: auto;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.data-table th {
  text-align: left;
  padding: 0.75rem 1rem;
  font-weight: 600;
  color: #374151;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
  background: #f8fafc;
}

.data-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  color: #374151;
}

.data-row {
  cursor: pointer;
  transition: background 0.1s;
}

.data-row:hover {
  background: #f8fafc;
}

.unit-number-cell {
  font-weight: 600;
}

.empty-cell {
  text-align: center;
  color: #94a3b8;
  padding: 2rem 0;
}

.modal-form { display: flex; flex-direction: column; gap: 1rem; min-width: 400px; }
</style>
