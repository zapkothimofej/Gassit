<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../api/axios'

interface DocumentTemplate {
  id: number
  name: string
  type: string
  version: number
  body: string
  is_active: boolean
  park_id: number | null
  park?: { id: number; name: string }
}

interface Park {
  id: number
  name: string
}

const router = useRouter()
const route = useRoute()
const templateId = Number(route.params.id)

const template = ref<DocumentTemplate | null>(null)
const parks = ref<Park[]>([])
const loading = ref(false)
const saving = ref(false)
const toast = ref('')

const form = ref({
  name: '',
  type: '',
  body: '',
  is_active: true,
  park_override: false,
  park_id: null as number | null,
})

const TEMPLATE_TYPES = [
  { value: 'contract', label: 'Contract' },
  { value: 'invoice', label: 'Invoice' },
  { value: 'dunning_level_1', label: 'Dunning Level 1' },
  { value: 'dunning_level_2', label: 'Dunning Level 2' },
  { value: 'dunning_level_3', label: 'Dunning Level 3' },
  { value: 'deposit_receipt', label: 'Deposit Receipt' },
  { value: 'termination_notice', label: 'Termination Notice' },
]

const VARIABLE_GROUPS = [
  {
    label: 'Customer',
    vars: [
      { key: 'customer_name', desc: 'Full name' },
      { key: 'customer_email', desc: 'Email address' },
      { key: 'customer_phone', desc: 'Phone number' },
      { key: 'customer_address', desc: 'Full address' },
      { key: 'customer_city', desc: 'City' },
      { key: 'customer_zip', desc: 'ZIP code' },
      { key: 'customer_company', desc: 'Company name' },
    ],
  },
  {
    label: 'Contract / Unit',
    vars: [
      { key: 'contract_number', desc: 'Contract reference' },
      { key: 'contract_start', desc: 'Start date' },
      { key: 'contract_end', desc: 'End date (if set)' },
      { key: 'unit_number', desc: 'Unit identifier' },
      { key: 'unit_size_m2', desc: 'Size in m²' },
      { key: 'rent_amount', desc: 'Monthly rent' },
      { key: 'deposit_amount', desc: 'Deposit amount' },
    ],
  },
  {
    label: 'Invoice / Payment',
    vars: [
      { key: 'invoice_number', desc: 'Invoice reference' },
      { key: 'invoice_date', desc: 'Invoice date' },
      { key: 'due_date', desc: 'Payment due date' },
      { key: 'invoice_amount', desc: 'Total amount' },
      { key: 'tax_amount', desc: 'VAT amount' },
      { key: 'dunning_fee', desc: 'Dunning fee' },
    ],
  },
  {
    label: 'Park / Company',
    vars: [
      { key: 'park_name', desc: 'Park name' },
      { key: 'park_address', desc: 'Park address' },
      { key: 'park_phone', desc: 'Park phone' },
      { key: 'park_email', desc: 'Park email' },
      { key: 'bank_iban', desc: 'Bank IBAN' },
      { key: 'bank_bic', desc: 'Bank BIC' },
      { key: 'bank_owner', desc: 'Account owner' },
      { key: 'today', desc: "Today's date" },
    ],
  },
]

async function loadTemplate() {
  loading.value = true
  try {
    const res = await api.get<DocumentTemplate>(`/document-templates/${templateId}`)
    template.value = res.data
    form.value = {
      name: res.data.name,
      type: res.data.type,
      body: res.data.body,
      is_active: res.data.is_active,
      park_override: res.data.park_id !== null,
      park_id: res.data.park_id,
    }
  } finally {
    loading.value = false
  }
}

async function loadParks() {
  try {
    const res = await api.get<Park[] | { data: Park[] }>('/parks')
    const data = res.data
    parks.value = Array.isArray(data) ? data : data.data
  } catch {
    parks.value = []
  }
}

onMounted(() => {
  loadTemplate()
  loadParks()
})

function insertVariable(key: string) {
  const textarea = document.getElementById('html-editor') as HTMLTextAreaElement | null
  if (!textarea) {
    form.value.body += `{{${key}}}`
    return
  }
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const before = form.value.body.slice(0, start)
  const after = form.value.body.slice(end)
  form.value.body = `${before}{{${key}}}${after}`
  setTimeout(() => {
    textarea.focus()
    textarea.setSelectionRange(start + key.length + 4, start + key.length + 4)
  }, 0)
}

async function save() {
  saving.value = true
  try {
    await api.put(`/document-templates/${templateId}`, {
      name: form.value.name,
      type: form.value.type,
      body: form.value.body,
      is_active: form.value.is_active,
      park_id: form.value.park_override ? form.value.park_id : null,
    })
    toast.value = 'Template saved.'
    setTimeout(() => (toast.value = ''), 3000)
  } finally {
    saving.value = false
  }
}

async function preview() {
  const res = await api.post<Blob>(`/document-templates/${templateId}/preview`, {}, { responseType: 'blob' })
  const url = URL.createObjectURL(res.data)
  window.open(url, '_blank')
}

function goBack() {
  router.push({ name: 'DocumentTemplates' })
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <div class="breadcrumb">
        <button class="back-btn" @click="goBack">← Document Templates</button>
        <span v-if="template" class="breadcrumb-sep">/</span>
        <span v-if="template" class="breadcrumb-current">{{ template.name }} (v{{ template.version }})</span>
      </div>
      <div class="header-actions">
        <button class="btn-secondary" @click="preview">Preview PDF</button>
        <button class="btn-primary" :disabled="saving" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </div>
    </div>

    <div v-if="toast" class="toast">{{ toast }}</div>

    <div v-if="loading" class="loading">Loading…</div>

    <div v-else class="editor-layout">
      <!-- Left: metadata -->
      <div class="meta-panel">
        <h3 class="panel-title">Template Settings</h3>

        <div class="field">
          <label>Name *</label>
          <input v-model="form.name" class="input" placeholder="Template name" />
        </div>

        <div class="field">
          <label>Type *</label>
          <select v-model="form.type" class="input">
            <option v-for="t in TEMPLATE_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
          </select>
        </div>

        <div class="field">
          <label class="checkbox-label">
            <input type="checkbox" v-model="form.is_active" />
            Active
          </label>
        </div>

        <div class="field">
          <label class="checkbox-label">
            <input type="checkbox" v-model="form.park_override" />
            Park-specific override
          </label>
          <select v-if="form.park_override" v-model="form.park_id" class="input" style="margin-top: 6px">
            <option :value="null">— Select park —</option>
            <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>

        <!-- Variable reference sidebar -->
        <div class="var-reference">
          <h4 class="var-ref-title">Variable Placeholders</h4>
          <div v-for="group in VARIABLE_GROUPS" :key="group.label" class="var-group">
            <p class="var-group-label">{{ group.label }}</p>
            <div v-for="v in group.vars" :key="v.key" class="var-item" @click="insertVariable(v.key)">
              <code class="var-code">{{ '{{' + v.key + '}}' }}</code>
              <span class="var-desc">{{ v.desc }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: HTML editor -->
      <div class="editor-panel">
        <label class="editor-label">HTML Body</label>
        <textarea
          id="html-editor"
          v-model="form.body"
          class="html-editor"
          placeholder="Enter HTML template here. Use {{variable}} placeholders from the sidebar."
          spellcheck="false"
        />
        <p class="editor-hint">Click a variable in the sidebar to insert it at the cursor position.</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; height: 100%; }
.page-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; }

.breadcrumb { display: flex; align-items: center; gap: 0.5rem; }
.back-btn { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 0.875rem; padding: 0; }
.back-btn:hover { text-decoration: underline; }
.breadcrumb-sep { color: #94a3b8; }
.breadcrumb-current { font-size: 0.875rem; color: #374151; font-weight: 500; }

.header-actions { display: flex; gap: 0.75rem; }
.btn-primary { background: #3b82f6; color: #fff; border: none; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; }
.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f1f5f9; color: #374151; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; }
.btn-secondary:hover { background: #e2e8f0; }

.toast { background: #22c55e; color: #fff; border-radius: 8px; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; align-self: flex-start; }
.loading { text-align: center; color: #94a3b8; padding: 2rem; }

.editor-layout { display: flex; gap: 1.25rem; flex: 1; min-height: 0; }

.meta-panel {
  flex: 0 0 280px;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  background: #fff;
  border-radius: 10px;
  padding: 1.25rem;
  box-shadow: 0 1px 4px rgba(0,0,0,.08);
  overflow-y: auto;
  max-height: calc(100vh - 160px);
}
.panel-title { font-size: 0.9rem; font-weight: 700; color: #1e293b; margin: 0 0 0.25rem; }

.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.checkbox-label { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: #374151; }
.input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.45rem 0.75rem; font-size: 0.875rem; outline: none; width: 100%; box-sizing: border-box; }
.input:focus { border-color: #3b82f6; }

.var-reference { border-top: 1px solid #e2e8f0; padding-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; }
.var-ref-title { font-size: 0.8rem; font-weight: 700; color: #374151; margin: 0; }
.var-group { display: flex; flex-direction: column; gap: 0.2rem; }
.var-group-label { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin: 0.25rem 0 0.15rem; }
.var-item { display: flex; align-items: baseline; gap: 0.4rem; cursor: pointer; padding: 2px 4px; border-radius: 4px; }
.var-item:hover { background: #eff6ff; }
.var-code { font-family: monospace; font-size: 0.7rem; color: #3b82f6; white-space: nowrap; }
.var-desc { font-size: 0.7rem; color: #64748b; }

.editor-panel { flex: 1; display: flex; flex-direction: column; gap: 0.5rem; min-width: 0; }
.editor-label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.html-editor {
  flex: 1;
  width: 100%;
  min-height: calc(100vh - 220px);
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 1rem;
  font-family: 'Fira Code', 'Cascadia Code', 'Consolas', monospace;
  font-size: 0.8rem;
  line-height: 1.6;
  resize: vertical;
  outline: none;
  color: #1e293b;
  background: #fafafa;
  box-sizing: border-box;
}
.html-editor:focus { border-color: #3b82f6; background: #fff; }
.editor-hint { font-size: 0.75rem; color: #94a3b8; margin: 0; }
</style>
