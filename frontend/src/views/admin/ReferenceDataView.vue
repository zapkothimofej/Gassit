<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import AppTable from '../../components/AppTable.vue'
import AppModal from '../../components/AppModal.vue'
import AppButton from '../../components/AppButton.vue'
import FormInput from '../../components/FormInput.vue'
import api from '../../api/axios'

interface ReferenceItem {
  id: number
  category: string
  value: string
  label: string
  sort_order: number
  active: boolean
}

const TABS: { key: string; label: string }[] = [
  { key: 'country', label: 'Länder' },
  { key: 'city', label: 'Städte' },
  { key: 'document_type', label: 'Dokumententypen' },
  { key: 'termination_reason', label: 'Kündigungsgründe' },
  { key: 'damage_category', label: 'Schadenskategorien' },
  { key: 'unit_feature', label: 'Einheitenmerkmale' },
  { key: 'industry_sector', label: 'Branchen' },
]

const activeTab = ref(TABS[0]?.key ?? 'country')
const items = ref<ReferenceItem[]>([])
const loading = ref(false)

const columns = [
  { key: 'sort_order', label: '#', sortable: false },
  { key: 'value', label: 'Wert', sortable: false },
  { key: 'label', label: 'Bezeichnung', sortable: false },
  { key: 'active', label: 'Aktiv', sortable: false },
  { key: 'actions', label: 'Aktionen', sortable: false },
]

async function load() {
  loading.value = true
  try {
    const res = await api.get('/reference-items', { params: { category: activeTab.value } })
    const data = res.data
    items.value = (Array.isArray(data) ? data : (data.data ?? [])) as ReferenceItem[]
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(activeTab, load)

// Add/Edit Modal
const showModal = ref(false)
const editItem = ref<ReferenceItem | null>(null)
const saving = ref(false)

const form = reactive({ value: '', label: '', sort_order: 0 })
const formErrors = reactive({ value: '', label: '' })

function openCreate() {
  editItem.value = null
  form.value = ''
  form.label = ''
  form.sort_order = items.value.length + 1
  formErrors.value = ''
  formErrors.label = ''
  showModal.value = true
}

function openEdit(item: ReferenceItem) {
  editItem.value = item
  form.value = item.value
  form.label = item.label
  form.sort_order = item.sort_order
  formErrors.value = ''
  formErrors.label = ''
  showModal.value = true
}

async function save() {
  formErrors.value = ''
  formErrors.label = ''
  if (!form.value.trim()) { formErrors.value = 'Pflichtfeld'; return }
  if (!form.label.trim()) { formErrors.label = 'Pflichtfeld'; return }

  saving.value = true
  try {
    if (editItem.value) {
      await api.put(`/reference-items/${editItem.value.id}`, {
        value: form.value,
        label: form.label,
        sort_order: form.sort_order,
      })
    } else {
      await api.post('/reference-items', {
        category: activeTab.value,
        value: form.value,
        label: form.label,
        sort_order: form.sort_order,
        active: true,
      })
    }
    showModal.value = false
    load()
  } finally {
    saving.value = false
  }
}

// Toggle active
const toggling = ref<number | null>(null)

async function toggleActive(item: ReferenceItem) {
  toggling.value = item.id
  try {
    await api.put(`/reference-items/${item.id}`, { active: !item.active })
    item.active = !item.active
  } finally {
    toggling.value = null
  }
}

// Deactivate (soft delete)
const deleting = ref<number | null>(null)

async function deactivate(item: ReferenceItem) {
  if (!confirm(`"${item.label}" deaktivieren?`)) return
  deleting.value = item.id
  try {
    await api.delete(`/reference-items/${item.id}`)
    load()
  } finally {
    deleting.value = null
  }
}
</script>

<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Referenzdaten</h1>
      <AppButton variant="primary" size="sm" @click="openCreate">+ Hinzufügen</AppButton>
    </div>

    <!-- Tab Bar -->
    <div class="border-b border-gray-200 mb-6">
      <nav class="flex space-x-1 overflow-x-auto">
        <button
          v-for="tab in TABS"
          :key="tab.key"
          class="px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
          :class="activeTab === tab.key
            ? 'border-blue-600 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- Table -->
    <div v-if="loading" class="text-center py-12 text-gray-400">Laden...</div>
    <AppTable
      v-else
      :columns="columns"
      :rows="(items as unknown as Record<string, unknown>[])"
    >
      <template #cell-sort_order="{ row }">
        <input
          type="number"
          class="w-16 border border-gray-300 rounded px-2 py-1 text-sm"
          :value="(row as unknown as ReferenceItem).sort_order"
          @blur="(e) => {
            const item = row as unknown as ReferenceItem
            const val = parseInt((e.target as HTMLInputElement).value)
            if (!isNaN(val) && val !== item.sort_order) {
              api.put(`/reference-items/${item.id}`, { sort_order: val }).then(() => { item.sort_order = val })
            }
          }"
        />
      </template>

      <template #cell-active="{ row }">
        <button
          class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors"
          :class="(row as unknown as ReferenceItem).active ? 'bg-green-500' : 'bg-gray-300'"
          :disabled="toggling === (row as unknown as ReferenceItem).id"
          @click="toggleActive(row as unknown as ReferenceItem)"
        >
          <span
            class="inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform"
            :class="(row as unknown as ReferenceItem).active ? 'translate-x-4' : 'translate-x-0.5'"
          />
        </button>
      </template>

      <template #cell-label="{ row }">
        <span :class="!(row as unknown as ReferenceItem).active ? 'text-gray-400 line-through' : ''">
          {{ (row as unknown as ReferenceItem).label }}
        </span>
      </template>

      <template #cell-value="{ row }">
        <span :class="!(row as unknown as ReferenceItem).active ? 'text-gray-400' : ''">
          {{ (row as unknown as ReferenceItem).value }}
        </span>
      </template>

      <template #cell-actions="{ row }">
        <div class="flex gap-2">
          <AppButton variant="ghost" size="sm" @click="openEdit(row as unknown as ReferenceItem)">
            Bearbeiten
          </AppButton>
          <AppButton
            variant="danger"
            size="sm"
            :loading="deleting === (row as unknown as ReferenceItem).id"
            @click="deactivate(row as unknown as ReferenceItem)"
          >
            Löschen
          </AppButton>
        </div>
      </template>

      <template #empty>
        <div class="text-center py-8 text-gray-400">Keine Einträge in dieser Kategorie.</div>
      </template>
    </AppTable>

    <!-- Add/Edit Modal -->
    <AppModal v-model="showModal" :title="editItem ? 'Eintrag bearbeiten' : 'Eintrag hinzufügen'">
      <div class="space-y-4">
        <FormInput
          v-model="form.value"
          label="Wert (intern)"
          :error="formErrors.value"
          required
        />
        <FormInput
          v-model="form.label"
          label="Bezeichnung (Anzeige)"
          :error="formErrors.label"
          required
        />
        <FormInput
          v-model.number="form.sort_order"
          label="Sortierung"
          type="number"
        />
      </div>
      <template #footer>
        <AppButton variant="ghost" @click="showModal = false">Abbrechen</AppButton>
        <AppButton variant="primary" :loading="saving" @click="save">Speichern</AppButton>
      </template>
    </AppModal>
  </div>
</template>
