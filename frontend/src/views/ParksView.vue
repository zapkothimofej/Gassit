<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import AppButton from '../components/AppButton.vue'
import AppModal from '../components/AppModal.vue'
import FormInput from '../components/FormInput.vue'
import StatusBadge from '../components/StatusBadge.vue'
import api from '../api/axios'

interface Park {
  id: number
  name: string
  address: string
  city: string
  zip: string
  country: string
  phone: string
  email: string
  bank_iban: string | null
  bank_bic: string | null
  bank_owner: string | null
  primary_color: string | null
  logo_path: string | null
}

interface LlmCode {
  id: number
  code: string
  description: string | null
  valid_from: string
  valid_to: string | null
  active: boolean
}

const parks = ref<Park[]>([])
const selectedParkId = ref<number | null>(null)
const selectedPark = computed(() => parks.value.find(p => p.id === selectedParkId.value) ?? null)

const toast = ref('')
function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

async function loadParks() {
  const res = await api.get<{ data: Park[] }>('/parks')
  parks.value = res.data.data ?? []
  const firstPark = parks.value[0]
  if (firstPark && !selectedParkId.value) {
    selectedParkId.value = firstPark.id
  }
}

// Park Form
const form = reactive({
  name: '',
  address: '',
  city: '',
  zip: '',
  country: '',
  phone: '',
  email: '',
  bank_iban: '',
  bank_bic: '',
  bank_owner: '',
  primary_color: '#3b82f6',
})

watch(selectedPark, (p) => {
  if (!p) return
  form.name = p.name
  form.address = p.address ?? ''
  form.city = p.city ?? ''
  form.zip = p.zip ?? ''
  form.country = p.country ?? ''
  form.phone = p.phone ?? ''
  form.email = p.email ?? ''
  form.bank_iban = p.bank_iban ?? ''
  form.bank_bic = p.bank_bic ?? ''
  form.bank_owner = p.bank_owner ?? ''
  form.primary_color = p.primary_color ?? '#3b82f6'
})

const saving = ref(false)
async function savePark() {
  if (!selectedParkId.value) return
  saving.value = true
  try {
    await api.put('/parks/' + selectedParkId.value, {
      name: form.name,
      address: form.address,
      city: form.city,
      zip: form.zip,
      country: form.country,
      phone: form.phone,
      email: form.email,
      bank_iban: form.bank_iban || null,
      bank_bic: form.bank_bic || null,
      bank_owner: form.bank_owner || null,
      primary_color: form.primary_color,
    })
    await loadParks()
    showToast('Park saved successfully.')
  } finally {
    saving.value = false
  }
}

// Logo Upload
const logoUploading = ref(false)
const logoPreview = computed(() => selectedPark.value?.logo_path ?? null)
const logoInput = ref<HTMLInputElement | null>(null)

async function onLogoChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !selectedParkId.value) return
  logoUploading.value = true
  try {
    const fd = new FormData()
    fd.append('logo', file)
    await api.post('/parks/' + selectedParkId.value + '/logo', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await loadParks()
    showToast('Logo updated.')
  } finally {
    logoUploading.value = false
  }
}

// LLM Access Codes
const codes = ref<LlmCode[]>([])
const loadingCodes = ref(false)
const syncing = ref(false)

async function loadCodes() {
  if (!selectedParkId.value) return
  loadingCodes.value = true
  try {
    const res = await api.get<LlmCode[]>('/parks/' + selectedParkId.value + '/access-codes')
    codes.value = Array.isArray(res.data) ? res.data : []
  } finally {
    loadingCodes.value = false
  }
}

watch(selectedParkId, () => { loadCodes() })

async function syncCodes() {
  if (!selectedParkId.value) return
  syncing.value = true
  try {
    await api.post('/parks/' + selectedParkId.value + '/access-codes/sync')
    showToast('Synced to LLM.')
  } finally {
    syncing.value = false
  }
}

// Add Code Modal
const showAddCodeModal = ref(false)
const addingCode = ref(false)
const codeForm = reactive({ code: '', description: '', valid_from: '', valid_to: '' })

async function submitAddCode() {
  if (!selectedParkId.value || !codeForm.code || !codeForm.valid_from) return
  addingCode.value = true
  try {
    await api.post('/parks/' + selectedParkId.value + '/access-codes', {
      code: codeForm.code,
      description: codeForm.description || null,
      valid_from: codeForm.valid_from,
      valid_to: codeForm.valid_to || null,
    })
    showAddCodeModal.value = false
    await loadCodes()
    showToast('Access code added.')
  } finally {
    addingCode.value = false
  }
}

async function deleteCode(code: LlmCode) {
  if (!selectedParkId.value) return
  await api.delete('/parks/' + selectedParkId.value + '/access-codes/' + code.id)
  await loadCodes()
  showToast('Code deleted.')
}

onMounted(loadParks)
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Park Profile</h2>
      <div class="header-right">
        <select v-if="parks.length > 1" v-model="selectedParkId" class="park-sel">
          <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <AppButton :loading="saving" @click="savePark">Save Changes</AppButton>
      </div>
    </div>

    <div v-if="toast" class="toast">{{ toast }}</div>

    <div v-if="selectedPark" class="content">
      <!-- Basic Info -->
      <div class="card">
        <h3 class="card-title">Basic Information</h3>
        <div class="form-grid">
          <FormInput label="Park Name *" :model-value="form.name" @update:model-value="form.name = $event" required />
          <FormInput label="Email" type="email" :model-value="form.email" @update:model-value="form.email = $event" />
          <FormInput label="Address" :model-value="form.address" @update:model-value="form.address = $event" />
          <FormInput label="Phone" :model-value="form.phone" @update:model-value="form.phone = $event" />
          <div class="row-3">
            <FormInput label="City" :model-value="form.city" @update:model-value="form.city = $event" />
            <FormInput label="ZIP" :model-value="form.zip" @update:model-value="form.zip = $event" />
            <FormInput label="Country" :model-value="form.country" @update:model-value="form.country = $event" />
          </div>
        </div>
      </div>

      <!-- Bank Details -->
      <div class="card">
        <h3 class="card-title">Bank Details</h3>
        <div class="form-grid">
          <FormInput label="IBAN" :model-value="form.bank_iban" @update:model-value="form.bank_iban = $event" placeholder="DE89 3704 0044..." />
          <FormInput label="BIC" :model-value="form.bank_bic" @update:model-value="form.bank_bic = $event" />
          <FormInput label="Account Owner" :model-value="form.bank_owner" @update:model-value="form.bank_owner = $event" />
        </div>
      </div>

      <!-- Branding -->
      <div class="card">
        <h3 class="card-title">Branding</h3>
        <div class="branding-row">
          <!-- Logo -->
          <div class="logo-section">
            <label class="field-label">Logo</label>
            <div class="logo-preview" @click="logoInput?.click()">
              <img v-if="logoPreview" :src="logoPreview" alt="Park logo" class="logo-img" />
              <div v-else class="logo-placeholder">
                <span>{{ logoUploading ? 'Uploading...' : 'Click to upload logo' }}</span>
              </div>
            </div>
            <input ref="logoInput" type="file" accept="image/*" class="hidden-input" @change="onLogoChange" />
          </div>
          <!-- Color -->
          <div class="color-section">
            <label class="field-label">Primary Color</label>
            <div class="color-row">
              <input v-model="form.primary_color" type="color" class="color-input" />
              <span class="color-preview" :style="{ background: form.primary_color }">{{ form.primary_color }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- LLM Access Codes -->
      <div class="card">
        <div class="llm-header">
          <h3 class="card-title">LLM Access Codes</h3>
          <div class="llm-actions">
            <AppButton variant="secondary" size="sm" :loading="syncing" @click="syncCodes">Sync to LLM</AppButton>
            <AppButton size="sm" @click="showAddCodeModal = true">+ Add Code</AppButton>
          </div>
        </div>
        <div v-if="loadingCodes" class="loading">Loading codes...</div>
        <div v-else-if="codes.length === 0" class="empty-text">No access codes configured.</div>
        <table v-else class="codes-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Description</th>
              <th>Valid From</th>
              <th>Valid To</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="code in codes" :key="code.id">
              <td class="mono">{{ code.code }}</td>
              <td>{{ code.description ?? '–' }}</td>
              <td>{{ code.valid_from }}</td>
              <td>{{ code.valid_to ?? '∞' }}</td>
              <td><StatusBadge :status="code.active ? 'active' : 'inactive'" /></td>
              <td>
                <button class="btn-delete" @click="deleteCode(code)">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add Code Modal -->
    <AppModal v-model="showAddCodeModal" title="Add LLM Access Code">
      <div class="modal-form">
        <FormInput label="Code *" :model-value="codeForm.code" @update:model-value="codeForm.code = $event" required />
        <FormInput label="Description" :model-value="codeForm.description" @update:model-value="codeForm.description = $event" />
        <div class="row-2">
          <FormInput label="Valid From *" type="date" :model-value="codeForm.valid_from" @update:model-value="codeForm.valid_from = $event" required />
          <FormInput label="Valid To" type="date" :model-value="codeForm.valid_to" @update:model-value="codeForm.valid_to = $event" />
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAddCodeModal = false">Cancel</AppButton>
        <AppButton :loading="addingCode" :disabled="!codeForm.code || !codeForm.valid_from" @click="submitAddCode">Add Code</AppButton>
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
.content { display: flex; flex-direction: column; gap: 1rem; }

.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; }
.card-title { margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; }

.form-grid { display: flex; flex-direction: column; gap: 0.875rem; }
.row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; }

.branding-row { display: flex; gap: 2rem; align-items: flex-start; }
.logo-section { flex: 1; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; display: block; margin-bottom: 0.5rem; }
.logo-preview { width: 160px; height: 90px; border: 2px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; background: #f8fafc; transition: border-color 0.15s; }
.logo-preview:hover { border-color: #3b82f6; }
.logo-img { width: 100%; height: 100%; object-fit: contain; }
.logo-placeholder { font-size: 0.75rem; color: #94a3b8; text-align: center; padding: 0.5rem; }
.hidden-input { display: none; }

.color-section { }
.color-row { display: flex; align-items: center; gap: 0.75rem; }
.color-input { width: 48px; height: 40px; border: 1px solid #cbd5e1; border-radius: 6px; cursor: pointer; padding: 2px; }
.color-preview { display: inline-flex; align-items: center; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.875rem; font-weight: 500; color: #fff; min-width: 80px; text-align: center; }

.llm-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.llm-header .card-title { margin: 0; }
.llm-actions { display: flex; gap: 0.5rem; }

.codes-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.codes-table th { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 2px solid #e2e8f0; color: #64748b; font-weight: 500; }
.codes-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f1f5f9; }
.mono { font-family: monospace; }
.btn-delete { border: 1px solid #ef4444; color: #ef4444; background: none; border-radius: 4px; padding: 0.2rem 0.5rem; font-size: 0.75rem; cursor: pointer; }
.btn-delete:hover { background: #fef2f2; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 380px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.loading { color: #64748b; font-size: 0.875rem; }
.empty-text { color: #94a3b8; font-size: 0.875rem; }
</style>
