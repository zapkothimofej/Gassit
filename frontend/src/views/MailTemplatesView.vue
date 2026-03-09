<script setup lang="ts">
import { ref, onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import api from '../api/axios'

interface MailTemplate {
  id: number
  name: string
  type: string
  subject: string
  body: string
  is_active: boolean
  park_id: number | null
  park?: { id: number; name: string }
}

interface Park {
  id: number
  name: string
}

const templates = ref<MailTemplate[]>([])
const parks = ref<Park[]>([])
const loading = ref(false)
const filterType = ref('')
const filterPark = ref<number | null>(null)

const showModal = ref(false)
const editingTemplate = ref<MailTemplate | null>(null)
const showPreview = ref(false)
const previewHtml = ref('')
const deleteConfirm = ref<number | null>(null)

const form = ref({
  name: '',
  type: 'welcome',
  subject: '',
  body: '',
  is_active: true,
  park_override: false,
  park_id: null as number | null,
})

const TEMPLATE_TYPES = [
  { value: 'welcome', label: 'Welcome' },
  { value: 'reminder', label: 'Reminder' },
  { value: 'dunning_level_1', label: 'Dunning Level 1' },
  { value: 'dunning_level_2', label: 'Dunning Level 2' },
  { value: 'dunning_level_3', label: 'Dunning Level 3' },
  { value: 'invoice', label: 'Invoice' },
  { value: 'contract', label: 'Contract' },
  { value: 'custom', label: 'Custom' },
]

const VARIABLES = [
  'customer_name', 'customer_email', 'unit_number', 'park_name',
  'rent_amount', 'due_date', 'invoice_number', 'contract_number',
  'deposit_amount', 'company_name',
]

const SAMPLE_DATA: Record<string, string> = {
  customer_name: 'Max Mustermann',
  customer_email: 'max@example.com',
  unit_number: 'A-42',
  park_name: 'Musterpark',
  rent_amount: '€ 89,00',
  due_date: '15.03.2026',
  invoice_number: 'INV-2026-0042',
  contract_number: 'C-2026-0010',
  deposit_amount: '€ 178,00',
  company_name: 'Musterfirma GmbH',
}

const editor = useEditor({
  extensions: [StarterKit],
  content: '',
  onUpdate({ editor: e }) {
    form.value.body = e.getHTML()
  },
})

onBeforeUnmount(() => editor.value?.destroy())

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {}
    if (filterType.value) params.type = filterType.value
    if (filterPark.value) params.park_id = filterPark.value
    const res = await api.get<MailTemplate[] | { data: MailTemplate[] }>('/mail-templates', { params })
    const data = res.data
    templates.value = Array.isArray(data) ? data : data.data
  } catch {
    templates.value = []
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

load()
loadParks()

watch([filterType, filterPark], load)

function openCreate() {
  editingTemplate.value = null
  form.value = { name: '', type: 'welcome', subject: '', body: '', is_active: true, park_override: false, park_id: null }
  editor.value?.commands.setContent('')
  showModal.value = true
}

function openEdit(t: MailTemplate) {
  editingTemplate.value = t
  form.value = {
    name: t.name,
    type: t.type,
    subject: t.subject,
    body: t.body,
    is_active: t.is_active,
    park_override: t.park_id !== null,
    park_id: t.park_id,
  }
  editor.value?.commands.setContent(t.body)
  showModal.value = true
}

function insertVariable(v: string) {
  editor.value?.commands.insertContent(`{{${v}}}`)
}

async function save() {
  const payload = {
    name: form.value.name,
    type: form.value.type,
    subject: form.value.subject,
    body: form.value.body,
    is_active: form.value.is_active,
    park_id: form.value.park_override ? form.value.park_id : null,
  }
  if (editingTemplate.value) {
    await api.put(`/mail-templates/${editingTemplate.value.id}`, payload)
  } else {
    await api.post('/mail-templates', payload)
  }
  showModal.value = false
  load()
}

async function toggleActive(t: MailTemplate) {
  await api.put(`/mail-templates/${t.id}`, { is_active: !t.is_active })
  t.is_active = !t.is_active
}

function openPreview(t: MailTemplate) {
  let html = t.body
  for (const [key, val] of Object.entries(SAMPLE_DATA)) {
    html = html.replace(new RegExp(`\\{\\{${key}\\}\\}`, 'g'), `<strong>${val}</strong>`)
  }
  previewHtml.value = html
  showPreview.value = true
}

async function deleteTemplate(id: number) {
  await api.delete(`/mail-templates/${id}`)
  deleteConfirm.value = null
  load()
}

function typeLabel(type: string) {
  return TEMPLATE_TYPES.find(t => t.value === type)?.label ?? type
}

const typeBadgeColor: Record<string, string> = {
  welcome: '#22c55e',
  reminder: '#f59e0b',
  dunning_level_1: '#f97316',
  dunning_level_2: '#ef4444',
  dunning_level_3: '#7f1d1d',
  invoice: '#3b82f6',
  contract: '#8b5cf6',
  custom: '#6b7280',
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Mail Templates</h1>
      <button class="btn-primary" @click="openCreate">+ New Template</button>
    </div>

    <div class="filter-bar">
      <select v-model="filterType" class="filter-select">
        <option value="">All Types</option>
        <option v-for="t in TEMPLATE_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
      </select>
      <select v-model="filterPark" class="filter-select">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Subject</th>
            <th>Park</th>
            <th>Active</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="6" class="empty">Loading…</td></tr>
          <tr v-else-if="!templates.length"><td colspan="6" class="empty">No templates found.</td></tr>
          <tr v-for="t in templates" :key="t.id">
            <td class="fw">{{ t.name }}</td>
            <td>
              <span class="badge" :style="{ background: typeBadgeColor[t.type] ?? '#6b7280' }">
                {{ typeLabel(t.type) }}
              </span>
            </td>
            <td class="subject-cell">{{ t.subject }}</td>
            <td>{{ t.park?.name ?? '—' }}</td>
            <td>
              <button
                class="toggle-btn"
                :class="t.is_active ? 'active' : 'inactive'"
                @click="toggleActive(t)"
              >{{ t.is_active ? 'Active' : 'Inactive' }}</button>
            </td>
            <td class="actions">
              <button class="btn-sm" @click="openPreview(t)">Preview</button>
              <button class="btn-sm" @click="openEdit(t)">Edit</button>
              <button class="btn-sm danger" @click="deleteConfirm = t.id">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="overlay" @click.self="showModal = false">
      <div class="modal wide">
        <h2 class="modal-title">{{ editingTemplate ? 'Edit Template' : 'New Template' }}</h2>

        <div class="modal-body">
          <div class="form-left">
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
              <label>Subject *</label>
              <input v-model="form.subject" class="input" placeholder="Email subject line" />
            </div>
            <div class="field">
              <label>
                <input type="checkbox" v-model="form.park_override" style="margin-right:6px" />
                Park-specific override
              </label>
              <select v-if="form.park_override" v-model="form.park_id" class="input" style="margin-top:6px">
                <option :value="null">— Select park —</option>
                <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div class="field">
              <label>
                <input type="checkbox" v-model="form.is_active" style="margin-right:6px" />
                Active
              </label>
            </div>
          </div>

          <div class="form-right">
            <label style="font-size:0.875rem;font-weight:500;color:#374151;margin-bottom:4px;display:block">Body</label>
            <div class="editor-toolbar">
              <button type="button" @click="editor?.chain().focus().toggleBold().run()">B</button>
              <button type="button" @click="editor?.chain().focus().toggleItalic().run()">I</button>
              <button type="button" @click="editor?.chain().focus().toggleBulletList().run()">• List</button>
              <button type="button" @click="editor?.chain().focus().toggleOrderedList().run()">1. List</button>
            </div>
            <EditorContent :editor="editor" class="editor-content" />

            <div class="var-helper">
              <p class="var-title">Insert variable:</p>
              <div class="var-chips">
                <button
                  v-for="v in VARIABLES"
                  :key="v"
                  type="button"
                  class="var-chip"
                  @click="insertVariable(v)"
                >{{ '{{' + v + '}}' }}</button>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-secondary" @click="showModal = false">Cancel</button>
          <button class="btn-primary" @click="save">Save</button>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <div v-if="showPreview" class="overlay" @click.self="showPreview = false">
      <div class="modal wide">
        <h2 class="modal-title">Template Preview</h2>
        <div class="preview-body" v-html="previewHtml"></div>
        <div class="modal-footer">
          <button class="btn-secondary" @click="showPreview = false">Close</button>
        </div>
      </div>
    </div>

    <!-- Delete Confirm -->
    <div v-if="deleteConfirm" class="overlay" @click.self="deleteConfirm = null">
      <div class="modal narrow">
        <h2 class="modal-title">Delete Template</h2>
        <p>Are you sure you want to delete this template?</p>
        <div class="modal-footer">
          <button class="btn-secondary" @click="deleteConfirm = null">Cancel</button>
          <button class="btn-danger" @click="deleteTemplate(deleteConfirm!)">Delete</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.filter-bar { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.filter-select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; outline: none; }
.card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; }
.table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.table th { background: #f8fafc; padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e2e8f0; }
.table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
.table tr:last-child td { border-bottom: none; }
.fw { font-weight: 500; }
.subject-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.empty { text-align: center; color: #94a3b8; padding: 2rem; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; color: #fff; }
.toggle-btn { padding: 3px 10px; border-radius: 12px; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 600; }
.toggle-btn.active { background: #dcfce7; color: #166534; }
.toggle-btn.inactive { background: #f1f5f9; color: #64748b; }
.actions { display: flex; gap: 0.4rem; }
.btn-sm { padding: 4px 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #374151; cursor: pointer; font-size: 0.8rem; }
.btn-sm:hover { background: #f8fafc; }
.btn-sm.danger { color: #ef4444; border-color: #fca5a5; }
.btn-sm.danger:hover { background: #fff1f2; }
.btn-primary { background: #3b82f6; color: #fff; border: none; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; }
.btn-primary:hover { background: #2563eb; }
.btn-secondary { background: #f1f5f9; color: #374151; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; }
.btn-danger { background: #ef4444; color: #fff; border: none; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; }

.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 999; }
.modal { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 8px 32px rgba(0,0,0,.2); display: flex; flex-direction: column; gap: 1rem; max-height: 90vh; overflow-y: auto; }
.modal.wide { width: min(900px, 95vw); }
.modal.narrow { width: min(400px, 95vw); }
.modal-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0; }
.modal-body { display: flex; gap: 1.5rem; }
.form-left { flex: 0 0 260px; display: flex; flex-direction: column; gap: 0.75rem; }
.form-right { flex: 1; display: flex; flex-direction: column; gap: 0.5rem; min-width: 0; }
.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.875rem; outline: none; width: 100%; box-sizing: border-box; }
.input:focus { border-color: #3b82f6; }

.editor-toolbar { display: flex; gap: 0.25rem; padding: 0.35rem; border: 1px solid #cbd5e1; border-bottom: none; border-radius: 6px 6px 0 0; background: #f8fafc; }
.editor-toolbar button { padding: 3px 8px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; cursor: pointer; font-size: 0.8rem; font-weight: 600; color: #374151; }
.editor-toolbar button:hover { background: #e2e8f0; }
:deep(.editor-content .ProseMirror) { border: 1px solid #cbd5e1; border-radius: 0 0 6px 6px; padding: 0.5rem 0.75rem; min-height: 160px; outline: none; font-size: 0.875rem; line-height: 1.5; }
:deep(.editor-content .ProseMirror:focus) { border-color: #3b82f6; }

.var-helper { border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; background: #f8fafc; }
.var-title { font-size: 0.75rem; font-weight: 600; color: #64748b; margin: 0 0 0.5rem; }
.var-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; }
.var-chip { background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 2px 8px; font-size: 0.75rem; color: #3b82f6; cursor: pointer; font-family: monospace; }
.var-chip:hover { background: #eff6ff; border-color: #93c5fd; }

.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; }
.preview-body { border: 1px solid #e2e8f0; border-radius: 6px; padding: 1.5rem; min-height: 200px; font-size: 0.9rem; line-height: 1.6; }
</style>
