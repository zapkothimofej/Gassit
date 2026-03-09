<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import AppTable from '../components/AppTable.vue'
import AppPagination from '../components/AppPagination.vue'
import AppModal from '../components/AppModal.vue'
import AppButton from '../components/AppButton.vue'
import StatusBadge from '../components/StatusBadge.vue'
import FormInput from '../components/FormInput.vue'
import FormSelect from '../components/FormSelect.vue'
import api from '../api/axios'
import { fetchParks } from '../api/parks'

interface Vendor {
  id: number
  name: string
  contact_name: string
  phone: string
  email: string
  specialty: string
  hourly_rate: string | null
  active: boolean
  park: { id: number; name: string } | null
}

interface VendorInvoice {
  id: number
  amount: string
  description: string
  status: string
  due_date: string | null
  damage_report_id: number | null
}

interface DamageReport {
  id: number
  description: string
  status: string
  unit: { unit_number: string }
}

const parks = ref<Array<{ id: number; name: string }>>([])
const vendors = ref<Vendor[]>([])
const totalPages = ref(1)
const page = ref(1)
const loading = ref(false)
const toast = ref('')

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

const columns = [
  { key: 'name', label: 'Name', sortable: false },
  { key: 'specialty', label: 'Specialty', sortable: false },
  { key: 'contact_name', label: 'Contact', sortable: false },
  { key: 'phone', label: 'Phone', sortable: false },
  { key: 'active', label: 'Status', sortable: false },
  { key: 'actions', label: '', sortable: false },
]

async function load() {
  loading.value = true
  try {
    const res = await api.get<{ data: Vendor[]; last_page: number }>('/vendors', {
      params: { page: page.value, per_page: 20 },
    })
    vendors.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

async function toggleActive(v: Vendor) {
  await api.put('/vendors/' + v.id, { active: !v.active })
  v.active = !v.active
}

// Vendor Detail Modal
const showDetailModal = ref(false)
const selectedVendor = ref<Vendor | null>(null)
const vendorInvoices = ref<VendorInvoice[]>([])
const vendorDamageReports = ref<DamageReport[]>([])
const loadingDetail = ref(false)
const detailTab = ref<'invoices' | 'damage'>('invoices')

async function openDetail(v: Vendor) {
  selectedVendor.value = v
  showDetailModal.value = true
  detailTab.value = 'invoices'
  loadingDetail.value = true
  try {
    const [invRes, dmgRes] = await Promise.allSettled([
      api.get<VendorInvoice[]>('/vendors/' + v.id + '/invoices'),
      api.get<DamageReport[]>('/vendors/' + v.id + '/damage-reports'),
    ])
    vendorInvoices.value = invRes.status === 'fulfilled'
      ? (Array.isArray(invRes.value.data) ? invRes.value.data : (invRes.value.data as unknown as { data: VendorInvoice[] }).data ?? [])
      : []
    vendorDamageReports.value = dmgRes.status === 'fulfilled'
      ? (Array.isArray(dmgRes.value.data) ? dmgRes.value.data : (dmgRes.value.data as unknown as { data: DamageReport[] }).data ?? [])
      : []
  } finally {
    loadingDetail.value = false
  }
}

const invColumns = [
  { key: 'description', label: 'Description', sortable: false },
  { key: 'amount', label: 'Amount', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'due_date', label: 'Due', sortable: false },
  { key: 'actions', label: '', sortable: false },
]

const dmgColumns = [
  { key: 'id', label: '#', sortable: false },
  { key: 'unit', label: 'Unit', sortable: false },
  { key: 'description', label: 'Description', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
]

const markingPaid = ref<number | null>(null)
async function markPaid(invId: number) {
  if (!selectedVendor.value) return
  markingPaid.value = invId
  try {
    await api.post('/vendors/' + selectedVendor.value.id + '/invoices/' + invId + '/pay')
    await refreshInvoices()
  } finally {
    markingPaid.value = null
  }
}

async function refreshInvoices() {
  if (!selectedVendor.value) return
  const res = await api.get<VendorInvoice[]>('/vendors/' + selectedVendor.value.id + '/invoices')
  vendorInvoices.value = Array.isArray(res.data) ? res.data : (res.data as unknown as { data: VendorInvoice[] }).data ?? []
}

// Add Vendor Invoice
const showAddInvModal = ref(false)
const addingInv = ref(false)
const invForm = reactive({
  amount: '',
  description: '',
  due_date: '',
  damage_report_id: '' as string | number,
})
const invPdfFile = ref<File | null>(null)

async function submitAddInvoice() {
  if (!selectedVendor.value) return
  addingInv.value = true
  try {
    const res = await api.post<VendorInvoice>('/vendors/' + selectedVendor.value.id + '/invoices', {
      amount: Number(invForm.amount),
      description: invForm.description,
      due_date: invForm.due_date || null,
      damage_report_id: invForm.damage_report_id ? Number(invForm.damage_report_id) : null,
    })
    if (invPdfFile.value) {
      const fd = new FormData()
      fd.append('file', invPdfFile.value)
      try {
        await api.put('/vendors/' + selectedVendor.value.id + '/invoices/' + res.data.id, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      } catch { /* PDF upload optional */ }
    }
    showAddInvModal.value = false
    invForm.amount = ''
    invForm.description = ''
    invForm.due_date = ''
    invForm.damage_report_id = ''
    invPdfFile.value = null
    await refreshInvoices()
    showToast('Invoice added.')
  } finally {
    addingInv.value = false
  }
}

const dmgOptions = computed(() =>
  vendorDamageReports.value.map(d => ({
    value: String(d.id),
    label: '#' + d.id + ' – ' + d.unit?.unit_number + ' – ' + d.description?.slice(0, 30),
  })),
)

// Create/Edit Vendor Modal
const showVendorModal = ref(false)
const editingVendor = ref<Vendor | null>(null)
const saving = ref(false)
const vForm = reactive({
  name: '',
  contact_name: '',
  phone: '',
  email: '',
  specialty: '',
  hourly_rate: '',
  park_id: null as number | null,
  active: true,
})

const ACTIVE_OPTIONS = [
  { value: 'true', label: 'Active' },
  { value: 'false', label: 'Inactive' },
]

function openCreate() {
  editingVendor.value = null
  vForm.name = ''
  vForm.contact_name = ''
  vForm.phone = ''
  vForm.email = ''
  vForm.specialty = ''
  vForm.hourly_rate = ''
  vForm.park_id = null
  vForm.active = true
  showVendorModal.value = true
}

function openEdit(v: Vendor) {
  editingVendor.value = v
  vForm.name = v.name
  vForm.contact_name = v.contact_name
  vForm.phone = v.phone
  vForm.email = v.email
  vForm.specialty = v.specialty
  vForm.hourly_rate = v.hourly_rate ?? ''
  vForm.park_id = v.park?.id ?? null
  vForm.active = v.active
  showVendorModal.value = true
}

async function saveVendor() {
  saving.value = true
  try {
    const payload = {
      name: vForm.name,
      contact_name: vForm.contact_name,
      phone: vForm.phone,
      email: vForm.email,
      specialty: vForm.specialty,
      hourly_rate: vForm.hourly_rate ? Number(vForm.hourly_rate) : null,
      park_id: vForm.park_id || null,
      active: vForm.active,
    }
    if (editingVendor.value) {
      await api.put('/vendors/' + editingVendor.value.id, payload)
    } else {
      await api.post('/vendors', payload)
    }
    showVendorModal.value = false
    await load()
    showToast(editingVendor.value ? 'Vendor updated.' : 'Vendor created.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Vendors</h2>
      <AppButton @click="openCreate">+ New Vendor</AppButton>
    </div>

    <div v-if="toast" class="toast">{{ toast }}</div>

    <AppTable
      :columns="columns"
      :rows="(vendors as unknown as Record<string, unknown>[])"
      @row-click="(row) => openDetail(row as unknown as Vendor)"
    >
      <template #cell-active="{ row }">
        <button class="toggle-btn" @click.stop="toggleActive(row as unknown as Vendor)">
          <StatusBadge :status="(row as unknown as Vendor).active ? 'active' : 'inactive'" />
        </button>
      </template>
      <template #cell-actions="{ row }">
        <button class="btn-sm" @click.stop="openEdit(row as unknown as Vendor)">Edit</button>
      </template>
      <template #empty>No vendors found.</template>
    </AppTable>

    <AppPagination
      :current-page="page"
      :total-pages="totalPages"
      @page-change="(p) => { page = p; load() }"
    />

    <!-- Vendor Detail Modal -->
    <AppModal v-model="showDetailModal" :title="selectedVendor?.name ?? 'Vendor'">
      <div class="vendor-detail" v-if="selectedVendor">
        <div class="info-grid">
          <div class="info-label">Specialty</div><div class="info-value">{{ selectedVendor.specialty }}</div>
          <div class="info-label">Contact</div><div class="info-value">{{ selectedVendor.contact_name }}</div>
          <div class="info-label">Phone</div><div class="info-value">{{ selectedVendor.phone }}</div>
          <div class="info-label">Email</div><div class="info-value">{{ selectedVendor.email }}</div>
          <div class="info-label">Rate</div><div class="info-value">{{ selectedVendor.hourly_rate ? selectedVendor.hourly_rate + ' €/h' : '–' }}</div>
          <div class="info-label">Park</div><div class="info-value">{{ selectedVendor.park?.name ?? 'All parks' }}</div>
        </div>

        <div class="tabs">
          <button :class="['tab-btn', { active: detailTab === 'invoices' }]" @click="detailTab = 'invoices'">
            Invoices ({{ vendorInvoices.length }})
          </button>
          <button :class="['tab-btn', { active: detailTab === 'damage' }]" @click="detailTab = 'damage'">
            Damage Reports ({{ vendorDamageReports.length }})
          </button>
        </div>

        <div v-if="loadingDetail" class="loading">Loading...</div>

        <!-- Invoices Tab -->
        <div v-else-if="detailTab === 'invoices'">
          <div class="tab-actions">
            <AppButton size="sm" @click="showAddInvModal = true">+ Add Invoice</AppButton>
          </div>
          <AppTable :columns="invColumns" :rows="(vendorInvoices as unknown as Record<string, unknown>[])">
            <template #cell-amount="{ row }">{{ (row as unknown as VendorInvoice).amount }} €</template>
            <template #cell-status="{ row }"><StatusBadge :status="(row as unknown as VendorInvoice).status" /></template>
            <template #cell-due_date="{ row }">{{ (row as unknown as VendorInvoice).due_date ?? '–' }}</template>
            <template #cell-actions="{ row }">
              <button
                v-if="(row as unknown as VendorInvoice).status === 'pending'"
                class="btn-pay"
                :disabled="markingPaid === (row as unknown as VendorInvoice).id"
                @click="markPaid((row as unknown as VendorInvoice).id)"
              >{{ markingPaid === (row as unknown as VendorInvoice).id ? '...' : 'Mark Paid' }}</button>
            </template>
            <template #empty>No invoices.</template>
          </AppTable>
        </div>

        <!-- Damage Reports Tab -->
        <div v-else-if="detailTab === 'damage'">
          <AppTable :columns="dmgColumns" :rows="(vendorDamageReports as unknown as Record<string, unknown>[])">
            <template #cell-id="{ row }"><span class="mono">#{{ (row as unknown as DamageReport).id }}</span></template>
            <template #cell-unit="{ row }">{{ (row as unknown as DamageReport).unit?.unit_number ?? '–' }}</template>
            <template #cell-description="{ row }">{{ (row as unknown as DamageReport).description?.slice(0, 50) }}</template>
            <template #cell-status="{ row }"><StatusBadge :status="(row as unknown as DamageReport).status" /></template>
            <template #empty>No damage reports.</template>
          </AppTable>
        </div>
      </div>
      <template #footer>
        <AppButton @click="showDetailModal = false">Close</AppButton>
      </template>
    </AppModal>

    <!-- Add Vendor Invoice Modal -->
    <AppModal v-model="showAddInvModal" title="Add Vendor Invoice">
      <div class="modal-form">
        <FormInput label="Amount (€) *" type="number" :model-value="invForm.amount" @update:model-value="invForm.amount = $event" required />
        <FormInput label="Description *" :model-value="invForm.description" @update:model-value="invForm.description = $event" required />
        <FormInput label="Due Date" type="date" :model-value="invForm.due_date" @update:model-value="invForm.due_date = $event" />
        <FormSelect
          v-if="dmgOptions.length > 0"
          label="Linked Damage Report"
          :model-value="String(invForm.damage_report_id)"
          @update:model-value="invForm.damage_report_id = $event"
          :options="[{ value: '', label: 'None' }, ...dmgOptions]"
        />
        <div>
          <label class="field-label">PDF (optional)</label>
          <input type="file" accept=".pdf" class="file-input" @change="(e) => { invPdfFile = (e.target as HTMLInputElement).files?.[0] ?? null }" />
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAddInvModal = false">Cancel</AppButton>
        <AppButton :loading="addingInv" :disabled="!invForm.amount || !invForm.description" @click="submitAddInvoice">Add Invoice</AppButton>
      </template>
    </AppModal>

    <!-- Create/Edit Vendor Modal -->
    <AppModal v-model="showVendorModal" :title="editingVendor ? 'Edit Vendor' : 'New Vendor'">
      <div class="modal-form">
        <FormInput label="Name *" :model-value="vForm.name" @update:model-value="vForm.name = $event" required />
        <FormInput label="Contact Name *" :model-value="vForm.contact_name" @update:model-value="vForm.contact_name = $event" required />
        <div class="row-2">
          <FormInput label="Phone *" :model-value="vForm.phone" @update:model-value="vForm.phone = $event" required />
          <FormInput label="Email *" type="email" :model-value="vForm.email" @update:model-value="vForm.email = $event" required />
        </div>
        <div class="row-2">
          <FormInput label="Specialty *" :model-value="vForm.specialty" @update:model-value="vForm.specialty = $event" required />
          <FormInput label="Hourly Rate (€)" type="number" :model-value="vForm.hourly_rate" @update:model-value="vForm.hourly_rate = $event" />
        </div>
        <div>
          <label class="field-label">Park</label>
          <select v-model="vForm.park_id" class="filter-sel full-w">
            <option :value="null">All parks</option>
            <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <FormSelect
          label="Status"
          :model-value="String(vForm.active)"
          @update:model-value="vForm.active = $event === 'true'"
          :options="ACTIVE_OPTIONS"
        />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showVendorModal = false">Cancel</AppButton>
        <AppButton :loading="saving" :disabled="!vForm.name || !vForm.contact_name" @click="saveVendor">
          {{ editingVendor ? 'Save' : 'Create' }}
        </AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.toast { background: #22c55e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 6px; text-align: center; }

.toggle-btn { border: none; background: none; cursor: pointer; padding: 0; }
.btn-sm { border: 1px solid #cbd5e1; background: none; border-radius: 4px; padding: 0.2rem 0.5rem; font-size: 0.75rem; cursor: pointer; }
.btn-sm:hover { background: #f1f5f9; }
.btn-pay { border: 1px solid #3b82f6; color: #3b82f6; background: none; border-radius: 4px; padding: 0.2rem 0.6rem; font-size: 0.8rem; cursor: pointer; }
.btn-pay:hover:not(:disabled) { background: #eff6ff; }
.btn-pay:disabled { opacity: 0.5; }

.vendor-detail { min-width: 540px; display: flex; flex-direction: column; gap: 1rem; }
.info-grid { display: grid; grid-template-columns: 100px 1fr; gap: 0.5rem 1rem; font-size: 0.875rem; }
.info-label { color: #64748b; }
.info-value { font-weight: 500; color: #1e293b; }

.tabs { display: flex; border-bottom: 2px solid #e2e8f0; }
.tab-btn { border: none; background: none; padding: 0.5rem 1rem; cursor: pointer; font-size: 0.875rem; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; font-weight: 500; }
.tab-actions { display: flex; justify-content: flex-end; margin-bottom: 0.5rem; }
.loading { color: #64748b; font-size: 0.875rem; }
.mono { font-family: monospace; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 420px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.full-w { width: 100%; box-sizing: border-box; }
.file-input { font-size: 0.875rem; }
</style>
