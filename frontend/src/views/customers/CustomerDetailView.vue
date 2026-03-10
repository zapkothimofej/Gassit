<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AppButton from '../../components/AppButton.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import FormInput from '../../components/FormInput.vue'
import AppModal from '../../components/AppModal.vue'
import api from '../../api/axios'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const id = Number(route.params.id)

interface Customer {
  id: number
  type: string
  status: string
  first_name: string
  last_name: string
  company_name: string | null
  email: string
  phone: string
  dob: string | null
  address: string
  zip: string
  city: string
  country: string
  id_number: string | null
  tax_id: string | null
  notes: string | null
  gdpr_consent_at: string | null
}

interface Contract {
  id: number
  status: string
  unit?: { unit_number: string }
  start_date: string | null
  terminated_at: string | null
  rent_amount: string
}

interface Invoice {
  id: number
  invoice_number: string | null
  total_amount: string
  status: string
  due_date: string | null
}

interface Application {
  id: number
  park?: { name: string }
  unit_type?: { name: string }
  status: string
  created_at: string
}

interface Document {
  id: number
  file_name: string
  file_path: string
  created_at: string
}

interface AuditEntry {
  id: number
  action: string
  user?: { name: string }
  created_at: string
}

const customer = ref<Customer | null>(null)
const contracts = ref<Contract[]>([])
const invoices = ref<Invoice[]>([])
const applications = ref<Application[]>([])
const documents = ref<Document[]>([])
const auditLogs = ref<AuditEntry[]>([])
const loading = ref(true)
const activeTab = ref('info')

async function load() {
  loading.value = true
  try {
    const res = await api.get('/customers/' + id)
    customer.value = res.data

    const [c, i, a, d, l] = await Promise.allSettled([
      api.get('/contracts', { params: { customer_id: id, per_page: 50 } }),
      api.get('/invoices', { params: { customer_id: id, per_page: 50 } }),
      api.get('/applications', { params: { customer_id: id, per_page: 50 } }),
      api.get('/customers/' + id + '/documents'),
      api.get('/audit-logs', { params: { model_type: 'Customer', model_id: id } }),
    ])
    if (c.status === 'fulfilled') contracts.value = c.value.data.data ?? c.value.data ?? []
    if (i.status === 'fulfilled') invoices.value = i.value.data.data ?? []
    if (a.status === 'fulfilled') applications.value = a.value.data.data ?? []
    if (d.status === 'fulfilled') documents.value = d.value.data.data ?? []
    if (l.status === 'fulfilled') auditLogs.value = l.value.data.data ?? []
  } finally {
    loading.value = false
  }
}

onMounted(load)

const displayName = computed(() => {
  if (!customer.value) return ''
  return customer.value.type === 'company' && customer.value.company_name
    ? customer.value.company_name
    : customer.value.first_name + ' ' + customer.value.last_name
})

// Edit form
const editForm = reactive<Partial<Customer>>({})
const saving = ref(false)

function startEdit() {
  if (!customer.value) return
  Object.assign(editForm, customer.value)
}

async function saveInfo() {
  saving.value = true
  try {
    await api.put('/customers/' + id, editForm)
    await load()
  } finally {
    saving.value = false
  }
}

// Document upload
const fileInput = ref<HTMLInputElement | null>(null)

async function uploadDoc(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) return
  const fd = new FormData()
  fd.append('file', file)
  await api.post('/customers/' + id + '/documents', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
  await load()
}

async function deleteDoc(docId: number) {
  await api.delete('/customers/' + id + '/documents/' + docId)
  documents.value = documents.value.filter(d => d.id !== docId)
}

// Actions
const actionLoading = ref<string | null>(null)

const showBlacklistModal = ref(false)
const blacklistReason = ref('')

async function addToBlacklist() {
  actionLoading.value = 'blacklist'
  try {
    await api.post('/customers/' + id + '/blacklist', { reason: blacklistReason.value })
    showBlacklistModal.value = false
    await load()
  } finally {
    actionLoading.value = null
  }
}

async function removeFromBlacklist() {
  actionLoading.value = 'unblacklist'
  try {
    await api.delete('/customers/' + id + '/blacklist')
    await load()
  } finally {
    actionLoading.value = null
  }
}

const showGdprModal = ref(false)

async function gdprDelete() {
  actionLoading.value = 'gdpr'
  try {
    await api.delete('/customers/' + id)
    router.push('/customers')
  } finally {
    actionLoading.value = null
  }
}

function fmt(d: string | null) {
  return d ? d.slice(0, 10) : '-'
}

onMounted(startEdit)
</script>

<template>
  <div v-if="loading" class="loading">Loading...</div>

  <div v-else-if="customer" class="profile-page">
    <!-- Header -->
    <div class="profile-header">
      <div class="back-link" @click="router.back()">← Back</div>
      <div class="header-info">
        <h2>{{ displayName }}</h2>
        <div class="badges">
          <span class="type-badge">{{ customer.type }}</span>
          <StatusBadge :status="customer.status" />
        </div>
      </div>
      <div class="header-actions">
        <AppButton
          v-if="customer.status !== 'blacklisted'"
          variant="danger"
          size="sm"
          :loading="actionLoading === 'blacklist'"
          @click="showBlacklistModal = true"
        >
          Add to Blacklist
        </AppButton>
        <AppButton
          v-else
          variant="secondary"
          size="sm"
          :loading="actionLoading === 'unblacklist'"
          @click="removeFromBlacklist"
        >
          Remove from Blacklist
        </AppButton>
        <AppButton
          v-if="auth.role === 'admin'"
          variant="danger"
          size="sm"
          @click="showGdprModal = true"
        >
          GDPR Delete
        </AppButton>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button
        v-for="tab in ['info', 'contracts', 'invoices', 'applications', 'documents', 'activity']"
        :key="tab"
        :class="['tab', { active: activeTab === tab }]"
        @click="activeTab = tab"
      >
        {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
      </button>
    </div>

    <!-- Personal Info -->
    <div v-if="activeTab === 'info'" class="card">
      <div class="form-grid">
        <FormInput label="First Name" :model-value="editForm.first_name ?? ''" @update:model-value="editForm.first_name = $event" />
        <FormInput label="Last Name" :model-value="editForm.last_name ?? ''" @update:model-value="editForm.last_name = $event" />
        <FormInput label="Email" type="email" :model-value="editForm.email ?? ''" @update:model-value="editForm.email = $event" />
        <FormInput label="Phone" :model-value="editForm.phone ?? ''" @update:model-value="editForm.phone = $event" />
        <FormInput label="Birthdate" type="date" :model-value="editForm.dob ?? ''" @update:model-value="editForm.dob = $event" />
        <FormInput label="Address" :model-value="editForm.address ?? ''" @update:model-value="editForm.address = $event" />
        <FormInput label="ZIP" :model-value="editForm.zip ?? ''" @update:model-value="editForm.zip = $event" />
        <FormInput label="City" :model-value="editForm.city ?? ''" @update:model-value="editForm.city = $event" />
        <FormInput label="Country" :model-value="editForm.country ?? ''" @update:model-value="editForm.country = $event" />
      </div>
      <div v-if="customer.gdpr_consent_at" class="gdpr-note">
        GDPR consent given: {{ fmt(customer.gdpr_consent_at) }}
      </div>
      <AppButton :loading="saving" @click="saveInfo">Save Changes</AppButton>
    </div>

    <!-- Contracts -->
    <div v-if="activeTab === 'contracts'" class="card">
      <table class="data-table">
        <thead><tr><th>ID</th><th>Status</th><th>Unit</th><th>Start</th><th>End</th><th>Rent</th></tr></thead>
        <tbody>
          <tr v-for="c in contracts" :key="c.id" class="clickable" @click="router.push('/contracts')">
            <td>#{{ c.id }}</td>
            <td><StatusBadge :status="c.status" /></td>
            <td>{{ c.unit?.unit_number ?? '-' }}</td>
            <td>{{ fmt(c.start_date) }}</td>
            <td>{{ fmt(c.terminated_at) }}</td>
            <td>{{ c.rent_amount }}</td>
          </tr>
          <tr v-if="!contracts.length"><td colspan="6" class="empty">No contracts</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Invoices -->
    <div v-if="activeTab === 'invoices'" class="card">
      <table class="data-table">
        <thead><tr><th>Number</th><th>Amount</th><th>Status</th><th>Due</th></tr></thead>
        <tbody>
          <tr v-for="inv in invoices" :key="inv.id" class="clickable" @click="router.push('/invoices')">
            <td>{{ inv.invoice_number ?? ('#' + inv.id) }}</td>
            <td>{{ parseFloat(inv.total_amount).toFixed(2) }}</td>
            <td><StatusBadge :status="inv.status" /></td>
            <td>{{ fmt(inv.due_date) }}</td>
          </tr>
          <tr v-if="!invoices.length"><td colspan="4" class="empty">No invoices</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Applications -->
    <div v-if="activeTab === 'applications'" class="card">
      <table class="data-table">
        <thead><tr><th>ID</th><th>Park</th><th>Unit Type</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
          <tr v-for="a in applications" :key="a.id" class="clickable" @click="router.push('/applications/' + a.id)">
            <td>#{{ a.id }}</td>
            <td>{{ a.park?.name ?? '-' }}</td>
            <td>{{ a.unit_type?.name ?? '-' }}</td>
            <td><StatusBadge :status="a.status" /></td>
            <td>{{ fmt(a.created_at) }}</td>
          </tr>
          <tr v-if="!applications.length"><td colspan="5" class="empty">No applications</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Documents -->
    <div v-if="activeTab === 'documents'" class="card">
      <div class="doc-actions">
        <input ref="fileInput" type="file" style="display:none" @change="uploadDoc" />
        <AppButton size="sm" variant="secondary" @click="fileInput?.click()">Upload Document</AppButton>
      </div>
      <table class="data-table">
        <thead><tr><th>File</th><th>Uploaded</th><th>Actions</th></tr></thead>
        <tbody>
          <tr v-for="doc in documents" :key="doc.id">
            <td><a :href="doc.file_path" target="_blank">{{ doc.file_name }}</a></td>
            <td>{{ fmt(doc.created_at) }}</td>
            <td><AppButton size="sm" variant="danger" @click="deleteDoc(doc.id)">Delete</AppButton></td>
          </tr>
          <tr v-if="!documents.length"><td colspan="3" class="empty">No documents</td></tr>
        </tbody>
      </table>
    </div>

    <!-- Activity -->
    <div v-if="activeTab === 'activity'" class="card">
      <div v-for="entry in auditLogs" :key="entry.id" class="timeline-entry">
        <span class="tl-action">{{ entry.action }}</span>
        <span class="tl-user">by {{ entry.user?.name ?? 'System' }}</span>
        <span class="tl-date">{{ fmt(entry.created_at) }}</span>
      </div>
      <div v-if="!auditLogs.length" class="empty">No activity</div>
    </div>

    <!-- Blacklist Modal -->
    <AppModal v-model="showBlacklistModal" title="Add to Blacklist">
      <FormInput label="Reason" :model-value="blacklistReason" @update:model-value="blacklistReason = $event" />
      <template #footer>
        <AppButton variant="secondary" @click="showBlacklistModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="actionLoading === 'blacklist'" @click="addToBlacklist">Confirm</AppButton>
      </template>
    </AppModal>

    <!-- GDPR Delete Modal -->
    <AppModal v-model="showGdprModal" title="GDPR Delete">
      <p>This will anonymize all personal data for this customer. This action cannot be undone.</p>
      <template #footer>
        <AppButton variant="secondary" @click="showGdprModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="actionLoading === 'gdpr'" @click="gdprDelete">Delete</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.loading { padding: 2rem; color: #64748b; }
.profile-page { display: flex; flex-direction: column; gap: 1.25rem; }
.profile-header { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.back-link { cursor: pointer; color: #3b82f6; font-size: 0.875rem; }
.header-info { flex: 1; }
.header-info h2 { margin: 0; }
.badges { display: flex; gap: 0.5rem; margin-top: 0.25rem; align-items: center; }
.type-badge { background: #f1f5f9; border-radius: 4px; padding: 0.15rem 0.5rem; font-size: 0.8rem; color: #475569; }
.header-actions { display: flex; gap: 0.5rem; }

.tabs { display: flex; gap: 0; border-bottom: 2px solid #e2e8f0; }
.tab { background: none; border: none; padding: 0.625rem 1.25rem; font-size: 0.875rem; cursor: pointer; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.tab.active { color: #3b82f6; border-bottom-color: #3b82f6; font-weight: 600; }

.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.875rem; }
.gdpr-note { font-size: 0.8rem; color: #64748b; background: #f8fafc; padding: 0.5rem 0.75rem; border-radius: 6px; }

.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th { background: #f8fafc; padding: 0.625rem 0.875rem; text-align: left; font-weight: 600; color: #475569; border-bottom: 1px solid #e2e8f0; }
.data-table td { padding: 0.625rem 0.875rem; border-bottom: 1px solid #f1f5f9; color: #374151; }
.clickable { cursor: pointer; }
.clickable:hover { background: #f8fafc; }
.empty { text-align: center; color: #94a3b8; padding: 1.5rem; }
.doc-actions { display: flex; justify-content: flex-end; }
.data-table td a { color: #3b82f6; text-decoration: none; }

.timeline-entry { display: flex; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; align-items: center; }
.tl-action { font-weight: 600; color: #374151; text-transform: capitalize; }
.tl-user { color: #64748b; }
.tl-date { color: #94a3b8; margin-left: auto; }
</style>
