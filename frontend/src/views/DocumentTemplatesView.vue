<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../api/axios'

interface DocumentTemplate {
  id: number
  name: string
  type: string
  version: number
  is_active: boolean
  park_id: number | null
  park?: { id: number; name: string }
  created_at: string
}

interface Park {
  id: number
  name: string
}

const router = useRouter()
const templates = ref<DocumentTemplate[]>([])
const parks = ref<Park[]>([])
const loading = ref(false)
const cloneConfirm = ref<DocumentTemplate | null>(null)

const TEMPLATE_TYPES = [
  { value: 'contract', label: 'Contract' },
  { value: 'invoice', label: 'Invoice' },
  { value: 'dunning_level_1', label: 'Dunning Level 1' },
  { value: 'dunning_level_2', label: 'Dunning Level 2' },
  { value: 'dunning_level_3', label: 'Dunning Level 3' },
  { value: 'deposit_receipt', label: 'Deposit Receipt' },
  { value: 'termination_notice', label: 'Termination Notice' },
]

const typeBadgeColor: Record<string, string> = {
  contract: '#8b5cf6',
  invoice: '#3b82f6',
  dunning_level_1: '#f97316',
  dunning_level_2: '#ef4444',
  dunning_level_3: '#7f1d1d',
  deposit_receipt: '#22c55e',
  termination_notice: '#64748b',
}

function typeLabel(type: string) {
  return TEMPLATE_TYPES.find(t => t.value === type)?.label ?? type
}

async function load() {
  loading.value = true
  try {
    const res = await api.get<DocumentTemplate[] | { data: DocumentTemplate[] }>('/document-templates')
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

const grouped = computed(() => {
  const groups: Record<string, DocumentTemplate[]> = {}
  for (const t of templates.value) {
    if (!groups[t.type]) groups[t.type] = []
    groups[t.type].push(t)
  }
  return groups
})

const groupKeys = computed(() => {
  const order = TEMPLATE_TYPES.map(t => t.value)
  return Object.keys(grouped.value).sort((a, b) => {
    const ai = order.indexOf(a)
    const bi = order.indexOf(b)
    return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi)
  })
})

async function toggleActive(t: DocumentTemplate) {
  await api.put(`/document-templates/${t.id}`, { is_active: !t.is_active })
  t.is_active = !t.is_active
}

async function cloneTemplate(t: DocumentTemplate) {
  await api.post(`/document-templates/${t.id}/clone`)
  cloneConfirm.value = null
  load()
}

async function previewTemplate(t: DocumentTemplate) {
  const res = await api.post<Blob>(`/document-templates/${t.id}/preview`, {}, { responseType: 'blob' })
  const url = URL.createObjectURL(res.data)
  window.open(url, '_blank')
}

function editTemplate(t: DocumentTemplate) {
  router.push({ name: 'DocumentTemplateEdit', params: { id: t.id } })
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h1 class="page-title">Document Templates</h1>
    </div>

    <div v-if="loading" class="loading">Loading…</div>

    <template v-else>
      <div v-if="groupKeys.length === 0" class="empty-state">No document templates found.</div>

      <div v-for="typeKey in groupKeys" :key="typeKey" class="group">
        <div class="group-header">
          <span class="badge" :style="{ background: typeBadgeColor[typeKey] ?? '#6b7280' }">
            {{ typeLabel(typeKey) }}
          </span>
          <span class="group-count">{{ grouped[typeKey].length }} template(s)</span>
        </div>

        <div class="card">
          <table class="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Version</th>
                <th>Park</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in grouped[typeKey]" :key="t.id">
                <td class="fw">{{ t.name }}</td>
                <td>
                  <span class="version-badge">v{{ t.version }}</span>
                </td>
                <td>{{ t.park?.name ?? '—' }}</td>
                <td>
                  <button
                    class="toggle-btn"
                    :class="t.is_active ? 'active' : 'inactive'"
                    @click="toggleActive(t)"
                  >{{ t.is_active ? 'Active' : 'Inactive' }}</button>
                </td>
                <td class="actions">
                  <button class="btn-sm" @click="previewTemplate(t)">Preview</button>
                  <button class="btn-sm" @click="editTemplate(t)">Edit</button>
                  <button class="btn-sm clone" @click="cloneConfirm = t">Clone</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Clone Confirm -->
    <div v-if="cloneConfirm" class="overlay" @click.self="cloneConfirm = null">
      <div class="modal narrow">
        <h2 class="modal-title">Clone Template</h2>
        <p>Clone <strong>{{ cloneConfirm.name }}</strong> (v{{ cloneConfirm.version }})? A new version will be created.</p>
        <div class="modal-footer">
          <button class="btn-secondary" @click="cloneConfirm = null">Cancel</button>
          <button class="btn-primary" @click="cloneTemplate(cloneConfirm!)">Clone</button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page { padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.loading { text-align: center; color: #94a3b8; padding: 2rem; }
.empty-state { text-align: center; color: #94a3b8; padding: 3rem; }

.group { display: flex; flex-direction: column; gap: 0.5rem; }
.group-header { display: flex; align-items: center; gap: 0.75rem; }
.group-count { font-size: 0.8rem; color: #64748b; }

.card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; }
.table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.table th { background: #f8fafc; padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e2e8f0; }
.table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
.table tr:last-child td { border-bottom: none; }
.fw { font-weight: 500; }

.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; color: #fff; }
.version-badge { display: inline-block; padding: 2px 8px; border-radius: 6px; background: #e0e7ff; color: #3730a3; font-size: 0.75rem; font-weight: 700; font-family: monospace; }

.toggle-btn { padding: 3px 10px; border-radius: 12px; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 600; }
.toggle-btn.active { background: #dcfce7; color: #166534; }
.toggle-btn.inactive { background: #f1f5f9; color: #64748b; }

.actions { display: flex; gap: 0.4rem; }
.btn-sm { padding: 4px 10px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; color: #374151; cursor: pointer; font-size: 0.8rem; }
.btn-sm:hover { background: #f8fafc; }
.btn-sm.clone { color: #8b5cf6; border-color: #c4b5fd; }
.btn-sm.clone:hover { background: #f5f3ff; }

.btn-primary { background: #3b82f6; color: #fff; border: none; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; }
.btn-primary:hover { background: #2563eb; }
.btn-secondary { background: #f1f5f9; color: #374151; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem 1.25rem; cursor: pointer; font-size: 0.875rem; }

.overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 999; }
.modal { background: #fff; border-radius: 12px; padding: 1.5rem; box-shadow: 0 8px 32px rgba(0,0,0,.2); display: flex; flex-direction: column; gap: 1rem; }
.modal.narrow { width: min(420px, 95vw); }
.modal-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0; }
.modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; }
</style>
