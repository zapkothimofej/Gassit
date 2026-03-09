<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '../components/AppButton.vue'
import AppModal from '../components/AppModal.vue'
import StatusBadge from '../components/StatusBadge.vue'
import FormInput from '../components/FormInput.vue'
import { searchCustomers } from '../api/applications'
import api from '../api/axios'

const router = useRouter()

interface BlacklistEntry {
  id: number
  customer?: { id: number; first_name: string; last_name: string }
  reason: string | null
  addedBy?: { name: string }
  created_at: string
  removed_at: string | null
}

const entries = ref<BlacklistEntry[]>([])
const activeOnly = ref(true)
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await api.get('/customers/blacklist')
    entries.value = res.data.data ?? res.data ?? []
  } finally {
    loading.value = false
  }
}

onMounted(load)

const filtered = computed(() =>
  activeOnly.value ? entries.value.filter(e => !e.removed_at) : entries.value,
)

// Remove modal
const showRemove = ref(false)
const removeTarget = ref<BlacklistEntry | null>(null)
const removeReason = ref('')
const removing = ref(false)

function openRemove(entry: BlacklistEntry) {
  removeTarget.value = entry
  removeReason.value = ''
  showRemove.value = true
}

async function confirmRemove() {
  if (!removeTarget.value?.customer?.id) return
  removing.value = true
  try {
    await api.delete('/customers/' + removeTarget.value.customer.id + '/blacklist', {
      data: { reason: removeReason.value },
    })
    showRemove.value = false
    await load()
  } finally {
    removing.value = false
  }
}

// Add modal
const showAdd = ref(false)
const addCustomerSearch = ref('')
const addCustomerResults = ref<Array<{ id: number; first_name: string; last_name: string; email: string }>>([])
const addCustomerId = ref<number | null>(null)
const addCustomerLabel = ref('')
const addReason = ref('')
const adding = ref(false)

let searchTimer: ReturnType<typeof setTimeout>
function onSearchInput() {
  clearTimeout(searchTimer)
  if (!addCustomerSearch.value) { addCustomerResults.value = []; return }
  searchTimer = setTimeout(async () => {
    const res = await searchCustomers(addCustomerSearch.value)
    addCustomerResults.value = res.data.data ?? []
  }, 300)
}

function selectAddCustomer(c: { id: number; first_name: string; last_name: string; email: string }) {
  addCustomerId.value = c.id
  addCustomerLabel.value = c.first_name + ' ' + c.last_name
  addCustomerResults.value = []
  addCustomerSearch.value = ''
}

async function confirmAdd() {
  if (!addCustomerId.value) return
  adding.value = true
  try {
    await api.post('/customers/' + addCustomerId.value + '/blacklist', { reason: addReason.value })
    showAdd.value = false
    addCustomerId.value = null
    addCustomerLabel.value = ''
    addReason.value = ''
    await load()
  } finally {
    adding.value = false
  }
}

function fmt(d: string | null) {
  return d ? d.slice(0, 10) : '-'
}

function customerName(e: BlacklistEntry) {
  if (!e.customer) return '-'
  return e.customer.first_name + ' ' + e.customer.last_name
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Blacklist</h2>
      <AppButton @click="showAdd = true">+ Add to Blacklist</AppButton>
    </div>

    <div class="filters">
      <label class="check-label">
        <input type="checkbox" v-model="activeOnly" />
        Active entries only
      </label>
    </div>

    <div class="table-wrapper">
      <table class="data-table">
        <thead>
          <tr>
            <th>Customer</th>
            <th>Reason</th>
            <th>Added By</th>
            <th>Added At</th>
            <th>Removed At</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7" class="empty">Loading...</td></tr>
          <tr v-else-if="!filtered.length"><td colspan="7" class="empty">No entries</td></tr>
          <tr
            v-for="entry in filtered"
            :key="entry.id"
            class="clickable"
            @click="entry.customer && router.push('/customers/' + entry.customer.id)"
          >
            <td>{{ customerName(entry) }}</td>
            <td>{{ entry.reason ?? '-' }}</td>
            <td>{{ entry.addedBy?.name ?? '-' }}</td>
            <td>{{ fmt(entry.created_at) }}</td>
            <td>{{ fmt(entry.removed_at) }}</td>
            <td>
              <StatusBadge :status="entry.removed_at ? 'removed' : 'blacklisted'" />
            </td>
            <td>
              <AppButton
                v-if="!entry.removed_at"
                size="sm"
                variant="secondary"
                @click.stop="openRemove(entry)"
              >
                Remove
              </AppButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Remove Modal -->
    <AppModal v-model="showRemove" title="Remove from Blacklist">
      <p>Remove <strong>{{ removeTarget ? customerName(removeTarget) : '' }}</strong> from blacklist?</p>
      <FormInput
        label="Reason (optional)"
        :model-value="removeReason"
        @update:model-value="removeReason = $event"
      />
      <template #footer>
        <AppButton variant="secondary" @click="showRemove = false">Cancel</AppButton>
        <AppButton variant="primary" :loading="removing" @click="confirmRemove">Remove</AppButton>
      </template>
    </AppModal>

    <!-- Add Modal -->
    <AppModal v-model="showAdd" title="Add to Blacklist">
      <div class="modal-form">
        <div class="form-field">
          <label class="field-label">Customer *</label>
          <input
            v-model="addCustomerSearch"
            class="form-input"
            placeholder="Search customer..."
            @input="onSearchInput"
          />
          <div v-if="addCustomerLabel" class="selected-item">
            {{ addCustomerLabel }}
            <button @click="addCustomerId = null; addCustomerLabel = ''" class="clear-btn">x</button>
          </div>
          <div v-if="addCustomerResults.length" class="dropdown">
            <div
              v-for="c in addCustomerResults"
              :key="c.id"
              class="dropdown-item"
              @click="selectAddCustomer(c)"
            >
              {{ c.first_name }} {{ c.last_name }} ({{ c.email }})
            </div>
          </div>
        </div>
        <FormInput
          label="Reason"
          :model-value="addReason"
          @update:model-value="addReason = $event"
        />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAdd = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="adding" @click="confirmAdd">Add</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.filters { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.check-label { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; cursor: pointer; }
.table-wrapper { overflow-x: auto; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { background: #f8fafc; padding: 0.75rem 1rem; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
.data-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #374151; }
.clickable { cursor: pointer; }
.clickable:hover { background: #f8fafc; }
.empty { text-align: center; color: #94a3b8; padding: 2rem; }
.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 360px; }
.form-field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.form-input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.875rem; }
.selected-item { background: #f0fdf4; border-radius: 4px; padding: 0.25rem 0.5rem; font-size: 0.8rem; display: flex; justify-content: space-between; }
.clear-btn { background: none; border: none; cursor: pointer; color: #94a3b8; }
.dropdown { border: 1px solid #e2e8f0; border-radius: 6px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.08); max-height: 180px; overflow-y: auto; }
.dropdown-item { padding: 0.5rem 0.75rem; font-size: 0.875rem; cursor: pointer; }
.dropdown-item:hover { background: #f8fafc; }
</style>
