<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import api from '../api/axios'

interface Park {
  id: number
  name: string
}

interface MailTemplate {
  id: number
  name: string
  type: string
  subject: string
}

const parks = ref<Park[]>([])
const templates = ref<MailTemplate[]>([])
const loading = ref(false)

const selectedParks = ref<number[]>([])
const selectedStatuses = ref<string[]>([])
const contractType = ref('')
const selectedTemplate = ref<number | null>(null)
const subjectOverride = ref('')
const estimatedCount = ref<number | null>(null)
const countLoading = ref(false)

const showSendConfirm = ref(false)
const showSchedule = ref(false)
const scheduleDateTime = ref('')
const sending = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

const CUSTOMER_STATUSES = [
  { value: 'new', label: 'New' },
  { value: 'tenant', label: 'Tenant' },
  { value: 'not_renting', label: 'Not Renting' },
  { value: 'debtor', label: 'Debtor' },
  { value: 'troublemaker', label: 'Troublemaker' },
]

const CONTRACT_TYPES = [
  { value: '', label: 'All Types' },
  { value: 'active', label: 'Active' },
  { value: 'terminated_by_customer', label: 'Terminated by Customer' },
  { value: 'terminated_by_lfg', label: 'Terminated by LFG' },
  { value: 'expired', label: 'Expired' },
]

const selectedTemplateObj = computed(() =>
  templates.value.find(t => t.id === selectedTemplate.value) ?? null
)

async function loadParks() {
  try {
    const res = await api.get<Park[] | { data: Park[] }>('/parks')
    const d = res.data
    parks.value = Array.isArray(d) ? d : d.data
  } catch {
    parks.value = []
  }
}

async function loadTemplates() {
  try {
    const res = await api.get<MailTemplate[] | { data: MailTemplate[] }>('/mail-templates', {
      params: { active: true },
    })
    const d = res.data
    templates.value = Array.isArray(d) ? d : d.data
  } catch {
    templates.value = []
  }
}

loadParks()
loadTemplates()

function togglePark(id: number) {
  const idx = selectedParks.value.indexOf(id)
  if (idx === -1) selectedParks.value.push(id)
  else selectedParks.value.splice(idx, 1)
}

function toggleStatus(s: string) {
  const idx = selectedStatuses.value.indexOf(s)
  if (idx === -1) selectedStatuses.value.push(s)
  else selectedStatuses.value.splice(idx, 1)
}

function filterPayload() {
  return {
    park_ids: selectedParks.value.length ? selectedParks.value : undefined,
    customer_statuses: selectedStatuses.value.length ? selectedStatuses.value : undefined,
    contract_status: contractType.value || undefined,
  }
}

async function refreshCount() {
  countLoading.value = true
  estimatedCount.value = null
  try {
    const res = await api.post<{ count: number }>('/mail/recipient-count', filterPayload())
    estimatedCount.value = res.data.count
  } catch {
    estimatedCount.value = null
  } finally {
    countLoading.value = false
  }
}

watch([selectedParks, selectedStatuses, contractType], refreshCount, { deep: true })
refreshCount()

function sendNow() {
  errorMsg.value = ''
  successMsg.value = ''
  if (!selectedTemplate.value) {
    errorMsg.value = 'Please select a template.'
    return
  }
  showSendConfirm.value = true
}

async function confirmSend() {
  sending.value = true
  errorMsg.value = ''
  try {
    await api.post('/mail/mass-send', {
      ...filterPayload(),
      template_id: selectedTemplate.value,
      subject: subjectOverride.value || undefined,
    })
    showSendConfirm.value = false
    successMsg.value = `Mass mailing sent to ${estimatedCount.value ?? 'all'} recipients.`
  } catch {
    errorMsg.value = 'Failed to send. Please try again.'
  } finally {
    sending.value = false
  }
}

async function confirmSchedule() {
  if (!scheduleDateTime.value) return
  sending.value = true
  errorMsg.value = ''
  try {
    await api.post('/mail/schedule', {
      ...filterPayload(),
      template_id: selectedTemplate.value,
      subject: subjectOverride.value || undefined,
      scheduled_at: scheduleDateTime.value,
    })
    showSchedule.value = false
    successMsg.value = `Mailing scheduled for ${scheduleDateTime.value}.`
  } catch {
    errorMsg.value = 'Failed to schedule. Please try again.'
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="compose">
    <h2 class="section-title">Compose Mass Mailing</h2>

    <div v-if="successMsg" class="alert success">{{ successMsg }}</div>
    <div v-if="errorMsg" class="alert error">{{ errorMsg }}</div>

    <div class="panel">
      <h3 class="panel-title">Recipient Filter</h3>

      <div class="field-group">
        <label class="field-label">Parks</label>
        <div class="chip-group">
          <button
            v-for="p in parks"
            :key="p.id"
            type="button"
            class="chip"
            :class="{ active: selectedParks.includes(p.id) }"
            @click="togglePark(p.id)"
          >{{ p.name }}</button>
          <span v-if="!parks.length" class="muted">No parks available</span>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label">Customer Status</label>
        <div class="chip-group">
          <button
            v-for="s in CUSTOMER_STATUSES"
            :key="s.value"
            type="button"
            class="chip"
            :class="{ active: selectedStatuses.includes(s.value) }"
            @click="toggleStatus(s.value)"
          >{{ s.label }}</button>
        </div>
      </div>

      <div class="field-group">
        <label class="field-label">Contract Status</label>
        <select v-model="contractType" class="input">
          <option v-for="c in CONTRACT_TYPES" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
      </div>

      <div class="recipient-count">
        <span class="count-label">Estimated recipients:</span>
        <span v-if="countLoading" class="count-value muted">Calculating…</span>
        <span v-else-if="estimatedCount !== null" class="count-value bold">{{ estimatedCount }}</span>
        <span v-else class="count-value muted">—</span>
      </div>
    </div>

    <div class="panel">
      <h3 class="panel-title">Message</h3>

      <div class="field-group">
        <label class="field-label">Template *</label>
        <select v-model="selectedTemplate" class="input">
          <option :value="null">— Select template —</option>
          <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.type }})</option>
        </select>
      </div>

      <div class="field-group">
        <label class="field-label">Subject Override</label>
        <input
          v-model="subjectOverride"
          class="input"
          :placeholder="selectedTemplateObj?.subject ?? 'Use template subject'"
        />
        <p class="field-hint">Leave empty to use template's default subject.</p>
      </div>
    </div>

    <div class="actions">
      <button class="btn-secondary" @click="showSchedule = true" :disabled="!selectedTemplate">
        Schedule
      </button>
      <button class="btn-primary" @click="sendNow" :disabled="!selectedTemplate">
        Send Now
      </button>
    </div>

    <!-- Send Confirm Modal -->
    <div v-if="showSendConfirm" class="overlay" @click.self="showSendConfirm = false">
      <div class="modal">
        <h2 class="modal-title">Confirm Mass Mailing</h2>
        <p class="confirm-text">
          This will send an email to
          <strong>{{ estimatedCount ?? '?' }} recipient(s)</strong>
          using template <strong>{{ selectedTemplateObj?.name }}</strong>.
        </p>
        <p class="confirm-text">This action cannot be undone.</p>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showSendConfirm = false">Cancel</button>
          <button class="btn-primary" :disabled="sending" @click="confirmSend">
            {{ sending ? 'Sending…' : 'Send' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Schedule Modal -->
    <div v-if="showSchedule" class="overlay" @click.self="showSchedule = false">
      <div class="modal">
        <h2 class="modal-title">Schedule Mailing</h2>
        <div class="field-group">
          <label class="field-label">Date &amp; Time *</label>
          <input v-model="scheduleDateTime" type="datetime-local" class="input" />
        </div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showSchedule = false">Cancel</button>
          <button class="btn-primary" :disabled="!scheduleDateTime || sending" @click="confirmSchedule">
            {{ sending ? 'Scheduling…' : 'Schedule' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.compose { display: flex; flex-direction: column; gap: 1.25rem; padding: 1.5rem; }
.section-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.panel { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.panel-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin: 0 0 0.25rem; }
.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.field-hint { font-size: 0.75rem; color: #94a3b8; margin: 0; }
.chip-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.chip { padding: 4px 12px; border-radius: 20px; border: 1px solid #cbd5e1; background: #fff; color: #374151; cursor: pointer; font-size: 0.8rem; transition: all .15s; }
.chip:hover { background: #f1f5f9; }
.chip.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
.input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; outline: none; width: 100%; box-sizing: border-box; }
.input:focus { border-color: #3b82f6; }
.recipient-count { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 0.75rem; background: #f8fafc; border-radius: 6px; font-size: 0.875rem; }
.count-label { color: #64748b; }
.count-value { color: #1e293b; }
.count-value.bold { font-weight: 700; color: #3b82f6; font-size: 1.1rem; }
.muted { color: #94a3b8; }
.actions { display: flex; justify-content: flex-end; gap: 0.75rem; }
.btn-primary { background: #3b82f6; color: #fff; border: none; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; }
.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-primary:disabled { opacity: .5; cursor: default; }
.btn-secondary { background: #f1f5f9; color: #374151; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; }
.btn-secondary:disabled { opacity: .5; cursor: default; }
.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); display: flex; align-items: center; justify-content: center; z-index: 50; }
.modal { background: #fff; border-radius: 12px; padding: 1.5rem; width: 440px; max-width: 95vw; }
.modal-title { font-size: 1.125rem; font-weight: 700; color: #1e293b; margin: 0 0 1rem; }
.confirm-text { font-size: 0.875rem; color: #334155; margin: 0 0 0.5rem; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.25rem; }
.alert { padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.875rem; }
.alert.success { background: #dcfce7; color: #166534; }
.alert.error { background: #fee2e2; color: #991b1b; }
</style>
