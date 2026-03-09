<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StatusBadge from '../components/StatusBadge.vue'
import AppButton from '../components/AppButton.vue'
import AppModal from '../components/AppModal.vue'
import FormSelect from '../components/FormSelect.vue'
import api from '../api/axios'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

interface Application {
  id: number
  status: string
  source: string
  notes: string | null
  desired_start_date: string | null
  credit_check_pdf_path: string | null
  customer?: { id: number; first_name: string; last_name: string; email: string; phone: string }
  unit_type?: { id: number; name: string }
  park?: { id: number; name: string }
  assigned_to?: { id: number; name: string }
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
  new_values: Record<string, unknown> | null
  created_at: string
}

const app = ref<Application | null>(null)
const documents = ref<Document[]>([])
const auditLogs = ref<AuditEntry[]>([])
const loading = ref(true)

const STEPS = ['new', 'in_progress', 'waiting', 'completed']
const TRANSITIONS: Record<string, string[]> = {
  new: ['in_progress'],
  in_progress: ['waiting', 'completed', 'declined'],
  waiting: ['completed', 'declined'],
  completed: [],
  declined: [],
}

const nextSteps = computed(() => TRANSITIONS[app.value?.status ?? ''] ?? [])
const currentStepIdx = computed(() => STEPS.indexOf(app.value?.status ?? ''))

async function load() {
  loading.value = true
  try {
    const res = await api.get('/applications/' + id)
    app.value = res.data
    if (app.value?.customer?.id) {
      const [docs, logs] = await Promise.allSettled([
        api.get('/customers/' + app.value.customer.id + '/documents'),
        api.get('/audit-logs', { params: { model_type: 'Application', model_id: id } }),
      ])
      if (docs.status === 'fulfilled') documents.value = docs.value.data.data ?? []
      if (logs.status === 'fulfilled') auditLogs.value = logs.value.data.data ?? []
    }
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Actions
const actionLoading = ref<string | null>(null)

async function changeStatus(status: string) {
  actionLoading.value = 'status'
  try {
    await api.put('/applications/' + id, { status })
    await load()
  } finally {
    actionLoading.value = null
  }
}

// Assign
const showAssignModal = ref(false)
const assignUserId = ref<number | null>(null)
const userOptions = ref<Array<{ id: number; name: string }>>([])

async function openAssign() {
  showAssignModal.value = true
  const res = await api.get('/admin/users')
  userOptions.value = res.data.data ?? []
}

async function submitAssign() {
  if (!assignUserId.value) return
  actionLoading.value = 'assign'
  try {
    await api.post('/applications/' + id + '/assign', { user_id: assignUserId.value })
    showAssignModal.value = false
    await load()
  } finally {
    actionLoading.value = null
  }
}

// Credit check
async function runCreditCheck() {
  actionLoading.value = 'credit'
  try {
    await api.post('/applications/' + id + '/credit-check')
    await load()
  } finally {
    actionLoading.value = null
  }
}

// Waiting list
async function moveToWaitingList() {
  actionLoading.value = 'waiting'
  try {
    await api.post('/applications/' + id + '/waiting-list')
    await load()
  } finally {
    actionLoading.value = null
  }
}

// Convert to contract
const showConvertModal = ref(false)
const selectedUnitId = ref<number | null>(null)
const availableUnits = ref<Array<{ id: number; unit_number: string }>>([])

async function openConvert() {
  showConvertModal.value = true
  if (app.value?.unit_type?.id && app.value?.park?.id) {
    const res = await api.get('/parks/' + app.value.park.id + '/units', {
      params: { status: 'free', unit_type_id: app.value.unit_type.id },
    })
    availableUnits.value = res.data.data ?? []
  }
}

async function submitConvert() {
  if (!selectedUnitId.value) return
  actionLoading.value = 'convert'
  try {
    await api.post('/applications/' + id + '/convert', { unit_id: selectedUnitId.value })
    showConvertModal.value = false
    await load()
  } finally {
    actionLoading.value = null
  }
}

// Document upload
const fileInput = ref<HTMLInputElement | null>(null)

async function uploadDocument(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file || !app.value?.customer?.id) return
  const formData = new FormData()
  formData.append('file', file)
  await api.post('/customers/' + app.value.customer.id + '/documents', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  await load()
}

function fmt(d: string) {
  return d ? d.slice(0, 10) : '-'
}
</script>

<template>
  <div v-if="loading" class="loading">Loading...</div>

  <div v-else-if="app" class="detail-page">
    <!-- Header -->
    <div class="detail-header">
      <div class="back-link" @click="router.back()">← Back</div>
      <h2>Application #{{ app.id }}</h2>
      <StatusBadge :status="app.status" />
    </div>

    <!-- Status stepper -->
    <div class="stepper">
      <div
        v-for="(step, idx) in ['new', 'in_progress', 'waiting', 'completed']"
        :key="step"
        :class="['step', { active: idx === currentStepIdx, done: idx < currentStepIdx }]"
      >
        <div class="step-dot" />
        <div class="step-label">{{ step.replace('_', ' ') }}</div>
      </div>
    </div>

    <div class="detail-body">
      <!-- Left: Cards -->
      <div class="cards">
        <!-- Customer card -->
        <div class="card" v-if="app.customer">
          <h3>Customer</h3>
          <div class="info-row"><span>Name</span><span>{{ app.customer.first_name }} {{ app.customer.last_name }}</span></div>
          <div class="info-row"><span>Email</span><span>{{ app.customer.email }}</span></div>
          <div class="info-row"><span>Phone</span><span>{{ app.customer.phone }}</span></div>
          <a :href="'/customers'" class="profile-link">View Profile</a>
        </div>

        <!-- Unit Request card -->
        <div class="card">
          <h3>Unit Request</h3>
          <div class="info-row"><span>Type</span><span>{{ app.unit_type?.name ?? '-' }}</span></div>
          <div class="info-row"><span>Park</span><span>{{ app.park?.name ?? '-' }}</span></div>
          <div class="info-row"><span>Start Date</span><span>{{ fmt(app.desired_start_date ?? '') }}</span></div>
          <div class="info-row"><span>Source</span><span>{{ app.source }}</span></div>
          <div v-if="app.notes" class="notes-box">{{ app.notes }}</div>
        </div>

        <!-- Credit Check -->
        <div class="card" v-if="app.credit_check_pdf_path">
          <h3>Credit Check</h3>
          <a :href="app.credit_check_pdf_path" target="_blank" class="pdf-link">View Report</a>
        </div>

        <!-- Documents -->
        <div class="card">
          <h3>Documents</h3>
          <div v-for="doc in documents" :key="doc.id" class="doc-row">
            <a :href="doc.file_path" target="_blank">{{ doc.file_name }}</a>
            <span class="doc-date">{{ fmt(doc.created_at) }}</span>
          </div>
          <div v-if="!documents.length" class="empty-text">No documents</div>
          <input ref="fileInput" type="file" style="display:none" @change="uploadDocument" />
          <AppButton size="sm" variant="secondary" @click="fileInput?.click()">Upload</AppButton>
        </div>

        <!-- Activity Timeline -->
        <div class="card">
          <h3>Activity</h3>
          <div v-for="entry in auditLogs" :key="entry.id" class="timeline-entry">
            <div class="tl-header">
              <span class="tl-action">{{ entry.action }}</span>
              <span class="tl-user">{{ entry.user?.name ?? 'System' }}</span>
              <span class="tl-date">{{ fmt(entry.created_at) }}</span>
            </div>
          </div>
          <div v-if="!auditLogs.length" class="empty-text">No activity yet</div>
        </div>
      </div>

      <!-- Right: Actions sidebar -->
      <div class="actions-sidebar">
        <h3>Actions</h3>

        <AppButton variant="secondary" size="sm" @click="openAssign">Assign</AppButton>

        <AppButton
          v-for="step in nextSteps"
          :key="step"
          :variant="step === 'declined' ? 'danger' : 'primary'"
          size="sm"
          :loading="actionLoading === 'status'"
          @click="changeStatus(step)"
        >
          Set {{ step.replace('_', ' ') }}
        </AppButton>

        <AppButton
          variant="secondary"
          size="sm"
          :loading="actionLoading === 'credit'"
          @click="runCreditCheck"
        >
          Run Credit Check
        </AppButton>

        <AppButton
          v-if="app.status === 'in_progress'"
          variant="secondary"
          size="sm"
          :loading="actionLoading === 'waiting'"
          @click="moveToWaitingList"
        >
          Move to Waiting List
        </AppButton>

        <AppButton
          v-if="app.status === 'in_progress' || app.status === 'waiting'"
          variant="primary"
          size="sm"
          :loading="actionLoading === 'convert'"
          @click="openConvert"
        >
          Convert to Contract
        </AppButton>
      </div>
    </div>

    <!-- Assign Modal -->
    <AppModal v-model="showAssignModal" title="Assign Application">
      <FormSelect
        label="Assign to"
        :model-value="assignUserId"
        :options="userOptions.map(u => ({ value: u.id, label: u.name }))"
        placeholder="Select user..."
        @update:model-value="assignUserId = Number($event)"
      />
      <template #footer>
        <AppButton variant="secondary" @click="showAssignModal = false">Cancel</AppButton>
        <AppButton :loading="actionLoading === 'assign'" @click="submitAssign">Assign</AppButton>
      </template>
    </AppModal>

    <!-- Convert Modal -->
    <AppModal v-model="showConvertModal" title="Convert to Contract">
      <FormSelect
        label="Select Unit"
        :model-value="selectedUnitId"
        :options="availableUnits.map(u => ({ value: u.id, label: u.unit_number }))"
        placeholder="Select free unit..."
        @update:model-value="selectedUnitId = Number($event)"
      />
      <template #footer>
        <AppButton variant="secondary" @click="showConvertModal = false">Cancel</AppButton>
        <AppButton :loading="actionLoading === 'convert'" @click="submitConvert">Convert</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.loading { padding: 2rem; color: #64748b; }
.detail-page { display: flex; flex-direction: column; gap: 1.5rem; }
.detail-header { display: flex; align-items: center; gap: 1rem; }
.detail-header h2 { margin: 0; }
.back-link { cursor: pointer; color: #3b82f6; font-size: 0.875rem; }

.stepper {
  display: flex;
  gap: 0;
  align-items: center;
}
.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  flex: 1;
  position: relative;
}
.step:not(:last-child)::after {
  content: '';
  position: absolute;
  top: 10px;
  left: 50%;
  width: 100%;
  height: 2px;
  background: #e2e8f0;
  z-index: 0;
}
.step.done::after { background: #3b82f6; }
.step-dot {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #e2e8f0;
  border: 2px solid #e2e8f0;
  z-index: 1;
}
.step.active .step-dot { background: #3b82f6; border-color: #3b82f6; }
.step.done .step-dot { background: #3b82f6; border-color: #3b82f6; }
.step-label { font-size: 0.7rem; color: #64748b; margin-top: 4px; text-transform: capitalize; }

.detail-body { display: grid; grid-template-columns: 1fr 240px; gap: 1.5rem; align-items: start; }
.cards { display: flex; flex-direction: column; gap: 1rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; }
.card h3 { margin: 0 0 0.875rem; font-size: 0.95rem; color: #1e293b; }
.info-row { display: flex; justify-content: space-between; padding: 0.3rem 0; font-size: 0.875rem; border-bottom: 1px solid #f8fafc; color: #374151; }
.info-row span:first-child { color: #64748b; }
.profile-link { font-size: 0.8rem; color: #3b82f6; text-decoration: none; margin-top: 0.5rem; display: inline-block; }
.notes-box { background: #f8fafc; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.85rem; color: #374151; margin-top: 0.5rem; }
.pdf-link { color: #3b82f6; font-size: 0.875rem; }
.doc-row { display: flex; justify-content: space-between; padding: 0.3rem 0; font-size: 0.875rem; }
.doc-row a { color: #3b82f6; text-decoration: none; }
.doc-date { color: #94a3b8; font-size: 0.8rem; }
.empty-text { color: #94a3b8; font-size: 0.85rem; padding: 0.5rem 0; }

.timeline-entry { padding: 0.5rem 0; border-bottom: 1px solid #f8fafc; }
.tl-header { display: flex; gap: 0.5rem; align-items: center; font-size: 0.8rem; }
.tl-action { font-weight: 600; color: #374151; text-transform: capitalize; }
.tl-user { color: #64748b; }
.tl-date { color: #94a3b8; margin-left: auto; }

.actions-sidebar {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}
.actions-sidebar h3 { margin: 0 0 0.5rem; font-size: 0.95rem; color: #1e293b; }
</style>
