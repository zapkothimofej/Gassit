<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import AppButton from '../components/AppButton.vue'
import AppModal from '../components/AppModal.vue'
import FormSelect from '../components/FormSelect.vue'
import FormInput from '../components/FormInput.vue'
import FormTextarea from '../components/FormTextarea.vue'
import {
  fetchWaitingList, addToWaitingList, updateWaitingListEntry,
  deleteWaitingListEntry, notifyWaitingListEntry, convertWaitingListEntry,
  type WaitingListEntry,
} from '../api/waitingList'
import { searchCustomers } from '../api/applications'
import { fetchParks, fetchUnitTypes } from '../api/parks'

const auth = useAuthStore()

const filterParkId = ref<number | null>(auth.parks[0]?.id ?? null)
const filterUnitTypeId = ref<number | null>(null)
const entries = ref<WaitingListEntry[]>([])
const parks = ref<Array<{ id: number; name: string }>>([])
const unitTypes = ref<Array<{ id: number; name: string; park_id: number }>>([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await fetchWaitingList(filterParkId.value, filterUnitTypeId.value)
    entries.value = res.data.data ?? []
  } finally {
    loading.value = false
  }
}

watch([filterParkId, filterUnitTypeId], load)

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

watch(filterParkId, async (pid) => {
  unitTypes.value = []
  filterUnitTypeId.value = null
  if (!pid) return
  const res = await fetchUnitTypes(pid)
  unitTypes.value = res.data.data ?? []
})

// Inline priority edit
async function savePriority(entry: WaitingListEntry, value: string) {
  const score = Number(value)
  if (isNaN(score)) return
  await updateWaitingListEntry(entry.id, { priority_score: score })
  entry.priority_score = score
}

// Actions
const actionLoading = ref<number | null>(null)

async function notify(entry: WaitingListEntry) {
  actionLoading.value = entry.id
  try {
    await notifyWaitingListEntry(entry.id)
    await load()
  } finally {
    actionLoading.value = null
  }
}

async function convert(entry: WaitingListEntry) {
  actionLoading.value = entry.id
  try {
    await convertWaitingListEntry(entry.id)
    await load()
  } finally {
    actionLoading.value = null
  }
}

async function remove(entry: WaitingListEntry) {
  actionLoading.value = entry.id
  try {
    await deleteWaitingListEntry(entry.id)
    entries.value = entries.value.filter(e => e.id !== entry.id)
  } finally {
    actionLoading.value = null
  }
}

// Add Modal
const showAdd = ref(false)
const addForm = ref({
  customer_id: null as number | null,
  unit_type_id: null as number | null,
  park_id: null as number | null,
  priority_score: 0,
  notes: null as string | null,
})
const adding = ref(false)
const customerSearch = ref('')
const customerResults = ref<Array<{ id: number; first_name: string; last_name: string; email: string }>>([])
const selectedCustomerLabel = ref('')
const addUnitTypes = ref<Array<{ id: number; name: string }>>([])

let searchTimer: ReturnType<typeof setTimeout>
watch(customerSearch, (q) => {
  clearTimeout(searchTimer)
  if (!q) { customerResults.value = []; return }
  searchTimer = setTimeout(async () => {
    const res = await searchCustomers(q)
    customerResults.value = res.data.data ?? []
  }, 300)
})

watch(() => addForm.value.park_id, async (pid) => {
  addUnitTypes.value = []
  addForm.value.unit_type_id = null
  if (!pid) return
  const res = await fetchUnitTypes(pid)
  addUnitTypes.value = res.data.data ?? []
})

function selectCustomer(c: { id: number; first_name: string; last_name: string; email: string }) {
  addForm.value.customer_id = c.id
  selectedCustomerLabel.value = c.first_name + ' ' + c.last_name
  customerResults.value = []
  customerSearch.value = ''
}

async function submitAdd() {
  if (!addForm.value.customer_id || !addForm.value.unit_type_id || !addForm.value.park_id) return
  adding.value = true
  try {
    await addToWaitingList({
      customer_id: addForm.value.customer_id,
      unit_type_id: addForm.value.unit_type_id,
      park_id: addForm.value.park_id,
      priority_score: addForm.value.priority_score,
      notes: addForm.value.notes || undefined,
    })
    showAdd.value = false
    await load()
  } finally {
    adding.value = false
  }
}

function fmt(d: string | null) {
  return d ? d.slice(0, 10) : '-'
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Waiting List</h2>
      <AppButton @click="showAdd = true">+ Add Entry</AppButton>
    </div>

    <!-- Filters -->
    <div class="filters">
      <select v-model="filterParkId" class="filter-sel">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filterUnitTypeId" class="filter-sel">
        <option :value="null">All Unit Types</option>
        <option v-for="u in unitTypes" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Unit Type</th>
            <th>Priority Score</th>
            <th>Added</th>
            <th>Notified</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7" class="empty">Loading...</td></tr>
          <tr v-else-if="!entries.length"><td colspan="7" class="empty">No entries</td></tr>
          <tr v-for="(entry, idx) in entries" :key="entry.id">
            <td>{{ idx + 1 }}</td>
            <td>{{ entry.customer?.first_name }} {{ entry.customer?.last_name }}</td>
            <td>{{ entry.unit_type?.name ?? '-' }}</td>
            <td>
              <input
                class="score-input"
                type="number"
                :value="entry.priority_score"
                @blur="savePriority(entry, ($event.target as HTMLInputElement).value)"
              />
            </td>
            <td>{{ fmt(entry.created_at) }}</td>
            <td>{{ fmt(entry.notified_at) }}</td>
            <td class="actions-cell">
              <AppButton size="sm" variant="secondary" :loading="actionLoading === entry.id" @click="notify(entry)">Notify</AppButton>
              <AppButton size="sm" variant="primary" :loading="actionLoading === entry.id" @click="convert(entry)">Convert</AppButton>
              <AppButton size="sm" variant="danger" :loading="actionLoading === entry.id" @click="remove(entry)">Remove</AppButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Modal -->
    <AppModal v-model="showAdd" title="Add to Waiting List">
      <div class="modal-form">
        <div class="form-field">
          <label class="field-label">Customer *</label>
          <input v-model="customerSearch" class="form-input" placeholder="Search customer..." />
          <div v-if="selectedCustomerLabel" class="selected-item">
            {{ selectedCustomerLabel }}
            <button @click="addForm.customer_id = null; selectedCustomerLabel = ''" class="clear-btn">x</button>
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
          :model-value="addForm.park_id"
          :options="parks.map(p => ({ value: p.id, label: p.name }))"
          placeholder="Select park..."
          @update:model-value="addForm.park_id = Number($event)"
        />

        <FormSelect
          label="Unit Type *"
          :model-value="addForm.unit_type_id"
          :options="addUnitTypes.map(u => ({ value: u.id, label: u.name }))"
          placeholder="Select unit type..."
          :disabled="!addForm.park_id"
          @update:model-value="addForm.unit_type_id = Number($event)"
        />

        <FormInput
          label="Priority Score"
          type="number"
          :model-value="String(addForm.priority_score)"
          @update:model-value="addForm.priority_score = Number($event)"
        />

        <FormTextarea
          label="Notes"
          :model-value="addForm.notes"
          @update:model-value="addForm.notes = $event"
        />
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showAdd = false">Cancel</AppButton>
        <AppButton :loading="adding" @click="submitAdd">Add</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.filters { display: flex; gap: 0.75rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.table-wrapper { overflow-x: auto; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { background: #f8fafc; padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
.data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #374151; }
.empty { text-align: center; color: #94a3b8; padding: 2rem; }
.score-input { width: 60px; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0.25rem 0.4rem; font-size: 0.875rem; text-align: center; }
.actions-cell { display: flex; gap: 0.375rem; }
.modal-form { display: flex; flex-direction: column; gap: 1rem; min-width: 360px; }
.form-field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.form-input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.875rem; }
.selected-item { background: #f0fdf4; border-radius: 4px; padding: 0.25rem 0.5rem; font-size: 0.8rem; display: flex; justify-content: space-between; }
.clear-btn { background: none; border: none; cursor: pointer; color: #94a3b8; }
.dropdown { border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); max-height: 180px; overflow-y: auto; }
.dropdown-item { padding: 0.5rem 0.75rem; font-size: 0.875rem; cursor: pointer; }
.dropdown-item:hover { background: #f8fafc; }
</style>
