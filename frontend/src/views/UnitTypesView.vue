<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue'
import AppButton from '../components/AppButton.vue'
import AppModal from '../components/AppModal.vue'
import FormInput from '../components/FormInput.vue'
import FormTextarea from '../components/FormTextarea.vue'
import StatusBadge from '../components/StatusBadge.vue'
import api from '../api/axios'
import { fetchParks } from '../api/parks'

interface UnitType {
  id: number
  name: string
  description: string | null
  base_rent: string
  deposit_amount: string
  size_m2: string
  floor_plan_path: string | null
  features: Array<{ id: number; feature: string }>
}

interface InsuranceOption {
  id: number
  name: string
  provider: string
  monthly_premium: string
  coverage_amount: string
  active: boolean
}

const parks = ref<Array<{ id: number; name: string }>>([])
const selectedParkId = ref<number | null>(null)
const unitTypes = ref<UnitType[]>([])
const loading = ref(false)
const toast = ref('')

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

onMounted(async () => {
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
  if (parks.value.length) selectedParkId.value = parks.value[0].id
})

watch(selectedParkId, () => { if (selectedParkId.value) loadUnitTypes() })

async function loadUnitTypes() {
  if (!selectedParkId.value) return
  loading.value = true
  try {
    const res = await api.get<UnitType[]>('/parks/' + selectedParkId.value + '/unit-types')
    unitTypes.value = Array.isArray(res.data) ? res.data : (res.data as unknown as { data: UnitType[] }).data ?? []
  } finally {
    loading.value = false
  }
}

// Create/Edit Modal
const showTypeModal = ref(false)
const editingType = ref<UnitType | null>(null)
const typeForm = reactive({
  name: '',
  description: '' as string | null,
  base_rent: '',
  deposit_amount: '',
  size_m2: '',
  features: [] as string[],
  newFeature: '',
})
const savingType = ref(false)

function openCreate() {
  editingType.value = null
  typeForm.name = ''
  typeForm.description = ''
  typeForm.base_rent = ''
  typeForm.deposit_amount = ''
  typeForm.size_m2 = ''
  typeForm.features = []
  typeForm.newFeature = ''
  showTypeModal.value = true
}

function openEdit(ut: UnitType) {
  editingType.value = ut
  typeForm.name = ut.name
  typeForm.description = ut.description ?? ''
  typeForm.base_rent = ut.base_rent
  typeForm.deposit_amount = ut.deposit_amount
  typeForm.size_m2 = ut.size_m2
  typeForm.features = ut.features.map(f => f.feature)
  typeForm.newFeature = ''
  showTypeModal.value = true
}

function addFeature() {
  const f = typeForm.newFeature.trim()
  if (f && !typeForm.features.includes(f)) typeForm.features.push(f)
  typeForm.newFeature = ''
}

function removeFeature(f: string) {
  typeForm.features = typeForm.features.filter(x => x !== f)
}

async function saveType() {
  if (!selectedParkId.value) return
  savingType.value = true
  try {
    const payload = {
      name: typeForm.name,
      description: typeForm.description || null,
      base_rent: Number(typeForm.base_rent),
      deposit_amount: Number(typeForm.deposit_amount),
      size_m2: Number(typeForm.size_m2),
    }
    let savedId: number
    if (editingType.value) {
      const res = await api.put<UnitType>('/parks/' + selectedParkId.value + '/unit-types/' + editingType.value.id, payload)
      savedId = res.data.id
    } else {
      const res = await api.post<UnitType>('/parks/' + selectedParkId.value + '/unit-types', payload)
      savedId = res.data.id
    }
    await api.post('/unit-types/' + savedId + '/features', { features: typeForm.features })
    showTypeModal.value = false
    await loadUnitTypes()
    showToast(editingType.value ? 'Unit type updated.' : 'Unit type created.')
  } finally {
    savingType.value = false
  }
}

async function deleteType(ut: UnitType) {
  if (!selectedParkId.value) return
  await api.delete('/parks/' + selectedParkId.value + '/unit-types/' + ut.id)
  await loadUnitTypes()
  showToast('Unit type deleted.')
}

// Floor Plan Upload
const floorPlanInput = ref<HTMLInputElement | null>(null)
const uploadingFloorPlan = ref<number | null>(null)

async function onFloorPlanChange(e: Event, unitTypeId: number) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  uploadingFloorPlan.value = unitTypeId
  try {
    const fd = new FormData()
    fd.append('floor_plan', file)
    await api.post('/unit-types/' + unitTypeId + '/floor-plan', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await loadUnitTypes()
    showToast('Floor plan uploaded.')
  } finally {
    uploadingFloorPlan.value = null
  }
}

// Insurance Options
const selectedTypeId = ref<number | null>(null)
const insuranceOptions = ref<InsuranceOption[]>([])
const loadingInsurance = ref(false)
const showInsuranceModal = ref(false)

async function openInsurance(ut: UnitType) {
  selectedTypeId.value = ut.id
  showInsuranceModal.value = true
  loadingInsurance.value = true
  try {
    const res = await api.get<InsuranceOption[]>('/parks/' + selectedParkId.value + '/insurance-options?unit_type_id=' + ut.id)
    insuranceOptions.value = Array.isArray(res.data) ? res.data : (res.data as unknown as { data: InsuranceOption[] }).data ?? []
  } finally {
    loadingInsurance.value = false
  }
}

const showAddInsuranceModal = ref(false)
const addingInsurance = ref(false)
const insForm = reactive({ name: '', provider: '', monthly_premium: '', coverage_amount: '' })

async function submitInsurance() {
  if (!selectedParkId.value || !selectedTypeId.value) return
  addingInsurance.value = true
  try {
    await api.post('/parks/' + selectedParkId.value + '/insurance-options', {
      unit_type_id: selectedTypeId.value,
      name: insForm.name,
      provider: insForm.provider,
      monthly_premium: Number(insForm.monthly_premium),
      coverage_amount: Number(insForm.coverage_amount),
    })
    showAddInsuranceModal.value = false
    const res = await api.get<InsuranceOption[]>('/parks/' + selectedParkId.value + '/insurance-options?unit_type_id=' + selectedTypeId.value)
    insuranceOptions.value = Array.isArray(res.data) ? res.data : (res.data as unknown as { data: InsuranceOption[] }).data ?? []
  } finally {
    addingInsurance.value = false
  }
}

async function toggleInsurance(ins: InsuranceOption) {
  if (!selectedParkId.value) return
  await api.put('/parks/' + selectedParkId.value + '/insurance-options/' + ins.id, { active: !ins.active })
  ins.active = !ins.active
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Unit Types</h2>
      <div class="header-right">
        <select v-if="parks.length > 1" v-model="selectedParkId" class="park-sel">
          <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <AppButton @click="openCreate">+ New Type</AppButton>
      </div>
    </div>

    <div v-if="toast" class="toast">{{ toast }}</div>

    <div v-if="loading" class="loading">Loading...</div>

    <div v-else class="types-grid">
      <div v-for="ut in unitTypes" :key="ut.id" class="type-card">
        <div class="type-header">
          <h3 class="type-name">{{ ut.name }}</h3>
          <div class="type-actions">
            <button class="btn-sm" @click="openEdit(ut)">Edit</button>
            <button class="btn-sm danger" @click="deleteType(ut)">Delete</button>
          </div>
        </div>

        <div class="type-stats">
          <div class="stat">
            <div class="stat-value">{{ ut.base_rent }} €</div>
            <div class="stat-label">Base Rent</div>
          </div>
          <div class="stat">
            <div class="stat-value">{{ ut.deposit_amount }} €</div>
            <div class="stat-label">Deposit</div>
          </div>
          <div class="stat">
            <div class="stat-value">{{ ut.size_m2 }} m²</div>
            <div class="stat-label">Size</div>
          </div>
        </div>

        <div v-if="ut.features.length > 0" class="features-row">
          <span v-for="f in ut.features" :key="f.id" class="feature-tag">{{ f.feature }}</span>
        </div>

        <div class="type-footer">
          <!-- Floor Plan -->
          <div class="floor-plan">
            <a v-if="ut.floor_plan_path" :href="ut.floor_plan_path" target="_blank" class="plan-link">View Floor Plan</a>
            <label class="upload-link">
              {{ uploadingFloorPlan === ut.id ? 'Uploading...' : (ut.floor_plan_path ? 'Replace' : 'Upload Floor Plan') }}
              <input type="file" accept=".pdf,image/*" class="hidden-input" @change="(e) => onFloorPlanChange(e, ut.id)" />
            </label>
          </div>
          <button class="btn-insurance" @click="openInsurance(ut)">Insurance Options</button>
        </div>
      </div>

      <div v-if="unitTypes.length === 0 && !loading" class="empty-state">
        No unit types configured for this park.
      </div>
    </div>

    <!-- Create/Edit Unit Type Modal -->
    <AppModal v-model="showTypeModal" :title="editingType ? 'Edit Unit Type' : 'New Unit Type'">
      <div class="modal-form">
        <FormInput label="Name *" :model-value="typeForm.name" @update:model-value="typeForm.name = $event" required />
        <FormTextarea label="Description" :model-value="typeForm.description" @update:model-value="typeForm.description = $event" :rows="2" />
        <div class="row-3">
          <FormInput label="Base Rent (€) *" type="number" :model-value="typeForm.base_rent" @update:model-value="typeForm.base_rent = $event" required />
          <FormInput label="Deposit (€) *" type="number" :model-value="typeForm.deposit_amount" @update:model-value="typeForm.deposit_amount = $event" required />
          <FormInput label="Size (m²) *" type="number" :model-value="typeForm.size_m2" @update:model-value="typeForm.size_m2 = $event" required />
        </div>
        <!-- Features -->
        <div class="features-section">
          <label class="field-label">Features</label>
          <div class="features-list">
            <span v-for="f in typeForm.features" :key="f" class="feature-tag removable">
              {{ f }}
              <button class="remove-feat" @click="removeFeature(f)">×</button>
            </span>
          </div>
          <div class="add-feature-row">
            <input v-model="typeForm.newFeature" class="feat-input" placeholder="Add feature..." @keydown.enter.prevent="addFeature" />
            <button class="btn-add-feat" @click="addFeature">Add</button>
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showTypeModal = false">Cancel</AppButton>
        <AppButton :loading="savingType" :disabled="!typeForm.name || !typeForm.base_rent" @click="saveType">
          {{ editingType ? 'Save Changes' : 'Create' }}
        </AppButton>
      </template>
    </AppModal>

    <!-- Insurance Options Modal -->
    <AppModal v-model="showInsuranceModal" title="Insurance Options">
      <div class="insurance-content">
        <div class="ins-header">
          <AppButton size="sm" @click="showAddInsuranceModal = true">+ Add Option</AppButton>
        </div>
        <div v-if="loadingInsurance" class="loading">Loading...</div>
        <div v-else-if="insuranceOptions.length === 0" class="empty-text">No insurance options configured.</div>
        <table v-else class="ins-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Provider</th>
              <th>Premium/mo</th>
              <th>Coverage</th>
              <th>Active</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="ins in insuranceOptions" :key="ins.id">
              <td>{{ ins.name }}</td>
              <td>{{ ins.provider }}</td>
              <td>{{ ins.monthly_premium }} €</td>
              <td>{{ ins.coverage_amount }} €</td>
              <td>
                <button class="toggle-btn" @click="toggleInsurance(ins)">
                  <StatusBadge :status="ins.active ? 'active' : 'inactive'" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <template #footer>
        <AppButton @click="showInsuranceModal = false">Close</AppButton>
      </template>
    </AppModal>

    <!-- Add Insurance Modal -->
    <AppModal v-model="showAddInsuranceModal" title="Add Insurance Option">
      <div class="modal-form">
        <FormInput label="Name *" :model-value="insForm.name" @update:model-value="insForm.name = $event" required />
        <FormInput label="Provider *" :model-value="insForm.provider" @update:model-value="insForm.provider = $event" required />
        <div class="row-2">
          <FormInput label="Monthly Premium (€) *" type="number" :model-value="insForm.monthly_premium" @update:model-value="insForm.monthly_premium = $event" required />
          <FormInput label="Coverage Amount (€) *" type="number" :model-value="insForm.coverage_amount" @update:model-value="insForm.coverage_amount = $event" required />
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAddInsuranceModal = false">Cancel</AppButton>
        <AppButton :loading="addingInsurance" :disabled="!insForm.name || !insForm.provider" @click="submitInsurance">Add</AppButton>
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
.loading { color: #64748b; font-size: 0.875rem; padding: 1rem 0; }

.types-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
.type-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; display: flex; flex-direction: column; gap: 0.875rem; }
.type-header { display: flex; justify-content: space-between; align-items: flex-start; }
.type-name { margin: 0; font-size: 1rem; font-weight: 600; color: #1e293b; }
.type-actions { display: flex; gap: 0.375rem; }
.btn-sm { border: 1px solid #cbd5e1; background: none; border-radius: 4px; padding: 0.2rem 0.5rem; font-size: 0.75rem; cursor: pointer; }
.btn-sm:hover { background: #f1f5f9; }
.btn-sm.danger { border-color: #ef4444; color: #ef4444; }
.btn-sm.danger:hover { background: #fef2f2; }

.type-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; }
.stat { text-align: center; background: #f8fafc; border-radius: 6px; padding: 0.5rem; }
.stat-value { font-size: 1rem; font-weight: 700; color: #1e293b; }
.stat-label { font-size: 0.7rem; color: #64748b; }

.features-row { display: flex; flex-wrap: wrap; gap: 0.375rem; }
.feature-tag { background: #eff6ff; color: #1d4ed8; font-size: 0.75rem; padding: 0.15rem 0.5rem; border-radius: 4px; }
.feature-tag.removable { display: flex; align-items: center; gap: 0.25rem; }
.remove-feat { border: none; background: none; cursor: pointer; color: #64748b; font-size: 0.9rem; padding: 0; line-height: 1; }

.type-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; }
.floor-plan { display: flex; gap: 0.5rem; align-items: center; font-size: 0.8rem; }
.plan-link { color: #3b82f6; text-decoration: none; }
.plan-link:hover { text-decoration: underline; }
.upload-link { color: #64748b; cursor: pointer; position: relative; }
.upload-link:hover { color: #3b82f6; }
.hidden-input { display: none; }
.btn-insurance { border: 1px solid #cbd5e1; background: none; border-radius: 4px; padding: 0.25rem 0.625rem; font-size: 0.8rem; cursor: pointer; color: #374151; }
.btn-insurance:hover { background: #f1f5f9; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 420px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem; }
.features-section { display: flex; flex-direction: column; gap: 0.5rem; }
.features-list { display: flex; flex-wrap: wrap; gap: 0.375rem; min-height: 28px; }
.add-feature-row { display: flex; gap: 0.5rem; }
.feat-input { flex: 1; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.btn-add-feat { border: 1px solid #3b82f6; color: #3b82f6; background: none; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; cursor: pointer; }
.btn-add-feat:hover { background: #eff6ff; }

.insurance-content { min-width: 480px; }
.ins-header { display: flex; justify-content: flex-end; margin-bottom: 0.75rem; }
.ins-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.ins-table th { text-align: left; padding: 0.5rem 0.75rem; border-bottom: 2px solid #e2e8f0; color: #64748b; font-weight: 500; }
.ins-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #f1f5f9; }
.toggle-btn { border: none; background: none; cursor: pointer; padding: 0; }
.empty-text { color: #94a3b8; font-size: 0.875rem; }
.empty-state { color: #94a3b8; text-align: center; padding: 3rem 0; }
</style>
