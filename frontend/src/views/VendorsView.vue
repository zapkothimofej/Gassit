<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppTable from '../components/AppTable.vue'
import AppPagination from '../components/AppPagination.vue'
import AppModal from '../components/AppModal.vue'
import AppButton from '../components/AppButton.vue'
import StatusBadge from '../components/StatusBadge.vue'
import FormInput from '../components/FormInput.vue'
import FormSelect from '../components/FormSelect.vue'
import api from '../api/axios'
import { fetchParks } from '../api/parks'

const router = useRouter()

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

const vendors = ref<Vendor[]>([])
const totalPages = ref(1)
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])

const columns = [
  { key: 'name', label: 'Name', sortable: false },
  { key: 'specialty', label: 'Specialty', sortable: false },
  { key: 'contact_name', label: 'Contact', sortable: false },
  { key: 'phone', label: 'Phone', sortable: false },
  { key: 'active', label: 'Status', sortable: false },
]

const page = ref(1)

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

// Vendor Detail Modal
const showDetailModal = ref(false)
const selectedVendor = ref<Vendor | null>(null)
const vendorInvoices = ref<VendorInvoice[]>([])
const loadingInvoices = ref(false)

async function openVendor(v: Vendor) {
  selectedVendor.value = v
  showDetailModal.value = true
  loadingInvoices.value = true
  try {
    const res = await api.get<VendorInvoice[]>('/vendors/' + v.id + '/invoices')
    vendorInvoices.value = Array.isArray(res.data) ? res.data : (res.data as unknown as { data: VendorInvoice[] }).data ?? []
  } finally {
    loadingInvoices.value = false
  }
}

const invColumns = [
  { key: 'description', label: 'Description', sortable: false },
  { key: 'amount', label: 'Amount', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'due_date', label: 'Due', sortable: false },
  { key: 'actions', label: '', sortable: false },
]

const markingPaid = ref<number | null>(null)
async function markPaid(invId: number) {
  if (!selectedVendor.value) return
  markingPaid.value = invId
  try {
    await api.post('/vendors/' + selectedVendor.value.id + '/invoices/' + invId + '/pay')
    const res = await api.get<VendorInvoice[]>('/vendors/' + selectedVendor.value.id + '/invoices')
    vendorInvoices.value = Array.isArray(res.data) ? res.data : (res.data as unknown as { data: VendorInvoice[] }).data ?? []
  } finally {
    markingPaid.value = null
  }
}

// Create Vendor Modal
const showCreateModal = ref(false)
const creating = ref(false)
const cForm = reactive({
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

async function submitCreate() {
  creating.value = true
  try {
    await api.post('/vendors', {
      name: cForm.name,
      contact_name: cForm.contact_name,
      phone: cForm.phone,
      email: cForm.email,
      specialty: cForm.specialty,
      hourly_rate: cForm.hourly_rate ? Number(cForm.hourly_rate) : null,
      park_id: cForm.park_id || null,
      active: cForm.active,
    })
    showCreateModal.value = false
    await load()
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Vendors</h2>
      <AppButton @click="showCreateModal = true">+ New Vendor</AppButton>
    </div>

    <AppTable
      :columns="columns"
      :rows="(vendors as unknown as Record<string, unknown>[])"
      @row-click="(row) => openVendor(row as unknown as Vendor)"
    >
      <template #cell-active="{ row }">
        <StatusBadge :status="(row as unknown as Vendor).active ? 'active' : 'inactive'" />
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
          <div class="info-label">Specialty</div>
          <div class="info-value">{{ selectedVendor.specialty }}</div>
          <div class="info-label">Contact</div>
          <div class="info-value">{{ selectedVendor.contact_name }}</div>
          <div class="info-label">Phone</div>
          <div class="info-value">{{ selectedVendor.phone }}</div>
          <div class="info-label">Email</div>
          <div class="info-value">{{ selectedVendor.email }}</div>
          <div class="info-label">Rate</div>
          <div class="info-value">{{ selectedVendor.hourly_rate ? selectedVendor.hourly_rate + ' €/h' : '–' }}</div>
          <div class="info-label">Park</div>
          <div class="info-value">{{ selectedVendor.park?.name ?? 'All parks' }}</div>
        </div>

        <h4 class="sub-title">Vendor Invoices</h4>
        <div v-if="loadingInvoices" class="loading">Loading...</div>
        <AppTable v-else :columns="invColumns" :rows="(vendorInvoices as unknown as Record<string, unknown>[])">
          <template #cell-amount="{ row }">
            {{ (row as unknown as VendorInvoice).amount }} €
          </template>
          <template #cell-status="{ row }">
            <StatusBadge :status="(row as unknown as VendorInvoice).status" />
          </template>
          <template #cell-due_date="{ row }">
            {{ (row as unknown as VendorInvoice).due_date ?? '–' }}
          </template>
          <template #cell-actions="{ row }">
            <button
              v-if="(row as unknown as VendorInvoice).status === 'pending'"
              class="btn-pay"
              :disabled="markingPaid === (row as unknown as VendorInvoice).id"
              @click.stop="markPaid((row as unknown as VendorInvoice).id)"
            >{{ markingPaid === (row as unknown as VendorInvoice).id ? '...' : 'Mark Paid' }}</button>
          </template>
          <template #empty>No invoices.</template>
        </AppTable>
      </div>
      <template #footer>
        <AppButton @click="showDetailModal = false">Close</AppButton>
      </template>
    </AppModal>

    <!-- Create Vendor Modal -->
    <AppModal v-model="showCreateModal" title="New Vendor">
      <div class="modal-form">
        <FormInput label="Name *" :model-value="cForm.name" @update:model-value="cForm.name = $event" required />
        <FormInput label="Contact Name *" :model-value="cForm.contact_name" @update:model-value="cForm.contact_name = $event" required />
        <div class="row-2">
          <FormInput label="Phone *" :model-value="cForm.phone" @update:model-value="cForm.phone = $event" required />
          <FormInput label="Email *" type="email" :model-value="cForm.email" @update:model-value="cForm.email = $event" required />
        </div>
        <div class="row-2">
          <FormInput label="Specialty *" :model-value="cForm.specialty" @update:model-value="cForm.specialty = $event" required />
          <FormInput label="Hourly Rate (€)" type="number" :model-value="cForm.hourly_rate" @update:model-value="cForm.hourly_rate = $event" />
        </div>
        <div>
          <label class="field-label">Park</label>
          <select v-model="cForm.park_id" class="filter-sel full-w">
            <option :value="null">All parks</option>
            <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <FormSelect
          label="Status"
          :model-value="String(cForm.active)"
          @update:model-value="cForm.active = $event === 'true'"
          :options="ACTIVE_OPTIONS"
        />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showCreateModal = false">Cancel</AppButton>
        <AppButton :loading="creating" @click="submitCreate">Create</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }

.vendor-detail { min-width: 480px; }
.info-grid { display: grid; grid-template-columns: 100px 1fr; gap: 0.5rem 1rem; font-size: 0.875rem; margin-bottom: 1rem; }
.info-label { color: #64748b; }
.info-value { font-weight: 500; color: #1e293b; }

.sub-title { font-size: 0.95rem; font-weight: 600; margin: 0 0 0.75rem 0; }
.loading { font-size: 0.875rem; color: #64748b; }

.btn-pay { border: 1px solid #3b82f6; color: #3b82f6; background: none; border-radius: 4px; padding: 0.2rem 0.6rem; font-size: 0.8rem; cursor: pointer; }
.btn-pay:hover:not(:disabled) { background: #eff6ff; }
.btn-pay:disabled { opacity: 0.5; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 420px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.full-w { width: 100%; box-sizing: border-box; }
</style>
