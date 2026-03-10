<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AppButton from '../../components/AppButton.vue'
import AppModal from '../../components/AppModal.vue'
import FormInput from '../../components/FormInput.vue'
import FormSelect from '../../components/FormSelect.vue'
import api from '../../api/axios'
import { fetchParks } from '../../api/parks'

interface DiscountRule {
  id: number
  name: string
  discount_type: string
  discount_value: string
  applies_from_month: number
  applies_to_month: number | null
  active: boolean
  unit_type_id: number | null
}

const parks = ref<Array<{ id: number; name: string }>>([])
const selectedParkId = ref<number | null>(null)
const rules = ref<DiscountRule[]>([])
const loading = ref(false)
const toast = ref('')

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
    await loadRules()
  }
})

async function loadRules() {
  if (!selectedParkId.value) return
  loading.value = true
  try {
    const res = await api.get<DiscountRule[]>('/parks/' + selectedParkId.value + '/discount-rules')
    rules.value = Array.isArray(res.data) ? res.data : (res.data as unknown as { data: DiscountRule[] }).data ?? []
  } finally {
    loading.value = false
  }
}

async function toggleActive(rule: DiscountRule) {
  if (!selectedParkId.value) return
  await api.put('/parks/' + selectedParkId.value + '/discount-rules/' + rule.id, { active: !rule.active })
  rule.active = !rule.active
}

async function deleteRule(rule: DiscountRule) {
  if (!selectedParkId.value) return
  await api.delete('/parks/' + selectedParkId.value + '/discount-rules/' + rule.id)
  rules.value = rules.value.filter(r => r.id !== rule.id)
  showToast('Discount rule deleted.')
}

const showModal = ref(false)
const editingRule = ref<DiscountRule | null>(null)
const saving = ref(false)
const form = reactive({
  name: '',
  discount_type: 'percentage',
  discount_value: '',
  applies_from_month: '1',
  applies_to_month: '',
})

const TYPE_OPTIONS = [
  { value: 'percentage', label: 'Percentage (%)' },
  { value: 'fixed', label: 'Fixed Amount (€)' },
]

const MONTH_OPTIONS = [
  { value: '1', label: 'Jan' }, { value: '2', label: 'Feb' }, { value: '3', label: 'Mar' },
  { value: '4', label: 'Apr' }, { value: '5', label: 'May' }, { value: '6', label: 'Jun' },
  { value: '7', label: 'Jul' }, { value: '8', label: 'Aug' }, { value: '9', label: 'Sep' },
  { value: '10', label: 'Oct' }, { value: '11', label: 'Nov' }, { value: '12', label: 'Dec' },
]

function openCreate() {
  editingRule.value = null
  form.name = ''
  form.discount_type = 'percentage'
  form.discount_value = ''
  form.applies_from_month = '1'
  form.applies_to_month = ''
  showModal.value = true
}

function openEdit(rule: DiscountRule) {
  editingRule.value = rule
  form.name = rule.name
  form.discount_type = rule.discount_type
  form.discount_value = rule.discount_value
  form.applies_from_month = String(rule.applies_from_month)
  form.applies_to_month = rule.applies_to_month ? String(rule.applies_to_month) : ''
  showModal.value = true
}

async function save() {
  if (!selectedParkId.value) return
  saving.value = true
  try {
    const payload = {
      name: form.name,
      discount_type: form.discount_type,
      discount_value: Number(form.discount_value),
      applies_from_month: Number(form.applies_from_month),
      applies_to_month: form.applies_to_month ? Number(form.applies_to_month) : null,
    }
    if (editingRule.value) {
      await api.put('/parks/' + selectedParkId.value + '/discount-rules/' + editingRule.value.id, payload)
    } else {
      await api.post('/parks/' + selectedParkId.value + '/discount-rules', payload)
    }
    showModal.value = false
    await loadRules()
    showToast(editingRule.value ? 'Rule updated.' : 'Rule created.')
  } finally {
    saving.value = false
  }
}

function monthName(m: number) {
  const names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  return names[m - 1] ?? m
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Discount Rules</h2>
      <div class="header-right">
        <select v-if="parks.length > 1" v-model="selectedParkId" class="park-sel" @change="loadRules()">
          <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <AppButton @click="openCreate">+ New Rule</AppButton>
      </div>
    </div>

    <div v-if="toast" class="toast">{{ toast }}</div>

    <div v-if="loading" class="loading">Loading...</div>
    <div v-else-if="rules.length === 0" class="empty-state">No discount rules configured.</div>
    <table v-else class="rules-table">
      <thead>
        <tr>
          <th>Name</th>
          <th>Type</th>
          <th>Value</th>
          <th>Months</th>
          <th>Active</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="rule in rules" :key="rule.id">
          <td class="rule-name">{{ rule.name }}</td>
          <td>
            <span class="type-badge">{{ rule.discount_type }}</span>
          </td>
          <td>
            {{ rule.discount_value }}{{ rule.discount_type === 'percentage' ? '%' : ' €' }}
          </td>
          <td>
            {{ monthName(rule.applies_from_month) }}
            <template v-if="rule.applies_to_month"> – {{ monthName(rule.applies_to_month) }}</template>
          </td>
          <td>
            <button class="toggle-btn" @click="toggleActive(rule)">
              <span :class="['status-pill', rule.active ? 'active' : 'inactive']">
                {{ rule.active ? 'Active' : 'Inactive' }}
              </span>
            </button>
          </td>
          <td>
            <div class="row-actions">
              <button class="btn-sm" @click="openEdit(rule)">Edit</button>
              <button class="btn-sm danger" @click="deleteRule(rule)">Delete</button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <AppModal v-model="showModal" :title="editingRule ? 'Edit Discount Rule' : 'New Discount Rule'">
      <div class="modal-form">
        <FormInput label="Name *" :model-value="form.name" @update:model-value="form.name = $event" required />
        <div class="row-2">
          <FormSelect label="Type *" :model-value="form.discount_type" @update:model-value="form.discount_type = $event" :options="TYPE_OPTIONS" />
          <FormInput
            :label="form.discount_type === 'percentage' ? 'Value (%) *' : 'Value (€) *'"
            type="number"
            :model-value="form.discount_value"
            @update:model-value="form.discount_value = $event"
            required
          />
        </div>
        <div class="row-2">
          <FormSelect label="From Month *" :model-value="form.applies_from_month" @update:model-value="form.applies_from_month = $event" :options="MONTH_OPTIONS" />
          <FormSelect label="To Month" :model-value="form.applies_to_month" @update:model-value="form.applies_to_month = $event" :options="[{ value: '', label: 'All year' }, ...MONTH_OPTIONS]" />
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="saving" :disabled="!form.name || !form.discount_value" @click="save">
          {{ editingRule ? 'Save' : 'Create' }}
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.header-right { display: flex; gap: 0.75rem; align-items: center; }
.park-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.toast { background: #22c55e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 6px; text-align: center; }
.loading { color: #64748b; font-size: 0.875rem; }
.empty-state { text-align: center; color: #94a3b8; padding: 3rem 0; }

.rules-table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; font-size: 0.875rem; }
.rules-table th { text-align: left; padding: 0.75rem 1rem; border-bottom: 2px solid #e2e8f0; color: #64748b; font-weight: 500; background: #f8fafc; }
.rules-table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; }
.rule-name { font-weight: 500; color: #1e293b; }
.type-badge { background: #eff6ff; color: #1d4ed8; font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 4px; text-transform: capitalize; }
.toggle-btn { border: none; background: none; cursor: pointer; padding: 0; }
.status-pill { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 500; }
.status-pill.active { background: #dcfce7; color: #15803d; }
.status-pill.inactive { background: #f1f5f9; color: #64748b; }
.row-actions { display: flex; gap: 0.375rem; }
.btn-sm { border: 1px solid #cbd5e1; background: none; border-radius: 4px; padding: 0.2rem 0.5rem; font-size: 0.75rem; cursor: pointer; }
.btn-sm:hover { background: #f1f5f9; }
.btn-sm.danger { border-color: #ef4444; color: #ef4444; }
.btn-sm.danger:hover { background: #fef2f2; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 400px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
</style>
