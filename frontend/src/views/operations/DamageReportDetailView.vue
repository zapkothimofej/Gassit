<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StatusBadge from '../../components/StatusBadge.vue'
import AppButton from '../../components/AppButton.vue'
import AppModal from '../../components/AppModal.vue'
import FormSelect from '../../components/FormSelect.vue'
import api from '../../api/axios'

const route = useRoute()
const router = useRouter()
const reportId = Number(route.params.id)

interface Photo {
  id: number
  path: string
  caption: string | null
}

interface DamageReportDetail {
  id: number
  status: string
  description: string
  estimated_cost: string | null
  actual_cost: string | null
  resolved_at: string | null
  created_at: string
  unit: { id: number; unit_number: string }
  reported_by: { id: number; name: string }
  assigned_vendor: { id: number; name: string } | null
  photos: Photo[]
}

interface Vendor {
  id: number
  name: string
}

const report = ref<DamageReportDetail | null>(null)
const vendors = ref<Vendor[]>([])
const loading = ref(true)
const toast = ref('')

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

async function load() {
  loading.value = true
  try {
    const res = await api.get<DamageReportDetail>('/damage-reports/' + reportId + '?with=unit,reportedBy,assignedVendor,photos')
    report.value = res.data
  } catch {
    const allRes = await api.get<{ data: DamageReportDetail[] }>('/damage-reports', { params: { per_page: 1000 } })
    report.value = allRes.data.data?.find(r => r.id === reportId) ?? null
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await load()
  const vRes = await api.get<{ data: Vendor[] }>('/vendors', { params: { per_page: 100, active: true } })
  vendors.value = vRes.data.data ?? []
})

const TRANSITIONS: Record<string, string[]> = {
  reported: ['in_assessment'],
  in_assessment: ['repair_ordered', 'reported'],
  repair_ordered: ['in_repair'],
  in_repair: ['resolved'],
  resolved: ['closed'],
  closed: [],
}

const STATUS_LABELS: Record<string, string> = {
  reported: 'Reported',
  in_assessment: 'In Assessment',
  repair_ordered: 'Repair Ordered',
  in_repair: 'In Repair',
  resolved: 'Resolved',
  closed: 'Closed',
}

const availableTransitions = computed(() =>
  report.value ? TRANSITIONS[report.value.status] ?? [] : [],
)

const changingStatus = ref(false)
async function changeStatus(newStatus: string) {
  changingStatus.value = true
  try {
    await api.put('/damage-reports/' + reportId + '/status', { status: newStatus })
    await load()
    showToast('Status updated.')
  } finally {
    changingStatus.value = false
  }
}

// Assign Vendor
const showAssignModal = ref(false)
const selectedVendorId = ref('')
const assigning = ref(false)

const vendorOptions = computed(() =>
  vendors.value.map(v => ({ value: String(v.id), label: v.name })),
)

async function doAssign() {
  assigning.value = true
  try {
    await api.post('/damage-reports/' + reportId + '/assign-vendor', { vendor_id: Number(selectedVendorId.value) })
    await load()
    showAssignModal.value = false
    showToast('Vendor assigned.')
  } finally {
    assigning.value = false
  }
}

// Generate Invoice
const showInvoiceConfirm = ref(false)
const generatingInvoice = ref(false)
async function doGenerateInvoice() {
  generatingInvoice.value = true
  try {
    await api.post('/damage-reports/' + reportId + '/invoice')
    showInvoiceConfirm.value = false
    showToast('Damage invoice generated and linked.')
  } finally {
    generatingInvoice.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="back-link">
      <button class="btn-back" @click="router.back()">← Back to Damage Reports</button>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <template v-else-if="report">
      <div class="page-header">
        <div>
          <h2>Damage Report #{{ report.id }}</h2>
          <StatusBadge :status="report.status" />
        </div>
        <div class="header-actions">
          <AppButton
            v-for="s in availableTransitions"
            :key="s"
            variant="secondary"
            size="sm"
            :loading="changingStatus"
            @click="changeStatus(s)"
          >{{ STATUS_LABELS[s] ?? s }}</AppButton>
          <AppButton variant="secondary" size="sm" @click="showAssignModal = true">
            {{ report.assigned_vendor ? 'Reassign Vendor' : 'Assign Vendor' }}
          </AppButton>
          <AppButton
            v-if="['in_repair', 'resolved', 'closed'].includes(report.status)"
            variant="secondary"
            size="sm"
            @click="showInvoiceConfirm = true"
          >Generate Invoice</AppButton>
        </div>
      </div>

      <div v-if="toast" class="toast">{{ toast }}</div>

      <div class="cards-grid">
        <!-- Info Card -->
        <div class="card">
          <h3 class="card-title">Report Details</h3>
          <div class="info-grid">
            <div class="info-label">Unit</div>
            <div class="info-value">
              <a class="link" @click="router.push('/units/' + report.unit.id)">{{ report.unit.unit_number }}</a>
            </div>
            <div class="info-label">Reported By</div>
            <div class="info-value">{{ report.reported_by?.name ?? '–' }}</div>
            <div class="info-label">Created</div>
            <div class="info-value">{{ report.created_at?.slice(0, 10) }}</div>
            <div class="info-label">Vendor</div>
            <div class="info-value">{{ report.assigned_vendor?.name ?? 'Not assigned' }}</div>
            <div class="info-label">Est. Cost</div>
            <div class="info-value">{{ report.estimated_cost ? report.estimated_cost + ' €' : '–' }}</div>
            <div class="info-label">Actual Cost</div>
            <div class="info-value">{{ report.actual_cost ? report.actual_cost + ' €' : '–' }}</div>
            <div v-if="report.resolved_at" class="info-label">Resolved At</div>
            <div v-if="report.resolved_at" class="info-value">{{ report.resolved_at?.slice(0, 10) }}</div>
          </div>
          <div class="description">
            <h4 class="desc-title">Description</h4>
            <p class="desc-text">{{ report.description }}</p>
          </div>
        </div>

        <!-- Photo Gallery -->
        <div class="card">
          <h3 class="card-title">Photos ({{ report.photos.length }})</h3>
          <div v-if="report.photos.length === 0" class="empty-text">No photos uploaded.</div>
          <div class="photo-grid">
            <div v-for="photo in report.photos" :key="photo.id" class="photo-item">
              <img :src="photo.path" :alt="photo.caption ?? 'Damage photo'" class="photo-img" />
              <div v-if="photo.caption" class="photo-caption">{{ photo.caption }}</div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Assign Vendor Modal -->
    <AppModal v-model="showAssignModal" title="Assign Vendor">
      <div class="modal-form">
        <FormSelect
          label="Vendor *"
          :model-value="selectedVendorId"
          @update:model-value="selectedVendorId = $event"
          :options="vendorOptions"
          placeholder="Select vendor..."
        />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAssignModal = false">Cancel</AppButton>
        <AppButton :loading="assigning" :disabled="!selectedVendorId" @click="doAssign">Assign</AppButton>
      </template>
    </AppModal>

    <!-- Generate Invoice Confirm -->
    <AppModal v-model="showInvoiceConfirm" title="Generate Damage Invoice">
      <p>Generate a tenant invoice for this damage report? The invoice will be charged to the tenant.</p>
      <template #footer>
        <AppButton variant="secondary" @click="showInvoiceConfirm = false">Cancel</AppButton>
        <AppButton :loading="generatingInvoice" @click="doGenerateInvoice">Generate</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.back-link { margin-bottom: 0.25rem; }
.btn-back { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 0.875rem; padding: 0; }
.btn-back:hover { text-decoration: underline; }

.page-header { display: flex; justify-content: space-between; align-items: flex-start; }
.page-header h2 { margin: 0 0 0.25rem 0; }
.header-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.toast { background: #22c55e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 6px; text-align: center; }

.cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; }
.card-title { margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; }

.info-grid { display: grid; grid-template-columns: 120px 1fr; gap: 0.5rem 1rem; font-size: 0.875rem; margin-bottom: 1rem; }
.info-label { color: #64748b; }
.info-value { color: #1e293b; font-weight: 500; }
.link { color: #3b82f6; cursor: pointer; }
.link:hover { text-decoration: underline; }

.description { margin-top: 0.75rem; }
.desc-title { font-size: 0.875rem; font-weight: 600; margin: 0 0 0.5rem 0; }
.desc-text { font-size: 0.875rem; color: #374151; white-space: pre-wrap; margin: 0; }

.empty-text { color: #94a3b8; font-size: 0.875rem; }
.photo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.5rem; }
.photo-item { border-radius: 6px; overflow: hidden; }
.photo-img { width: 100%; height: 90px; object-fit: cover; display: block; }
.photo-caption { font-size: 0.75rem; color: #64748b; padding: 0.25rem 0.375rem; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 340px; }
.loading { color: #64748b; padding: 2rem; text-align: center; }
</style>
