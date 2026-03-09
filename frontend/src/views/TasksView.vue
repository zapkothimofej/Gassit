<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import AppModal from '../components/AppModal.vue'
import AppButton from '../components/AppButton.vue'
import FormInput from '../components/FormInput.vue'
import FormSelect from '../components/FormSelect.vue'
import FormTextarea from '../components/FormTextarea.vue'
import api from '../api/axios'
import { fetchParks, fetchUsers } from '../api/parks'

const auth = useAuthStore()

interface Task {
  id: number
  title: string
  type: string
  priority: string
  status: string
  due_date: string | null
  description: string | null
  assigned_to: { id: number; name: string } | null
  park: { id: number; name: string } | null
  created_by: { id: number; name: string } | null
}

const tasks = ref<Task[]>([])
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])
const users = ref<Array<{ id: number; name: string }>>([])

const filters = reactive({
  assigned_to: '' as string,
  type: '',
  priority: '',
})

const TYPE_OPTIONS = [
  { value: '', label: 'All Types' },
  { value: 'application', label: 'Application' },
  { value: 'damage', label: 'Damage' },
  { value: 'ticket', label: 'Ticket' },
  { value: 'general', label: 'General' },
  { value: 'inspection', label: 'Inspection' },
  { value: 'renewal', label: 'Renewal' },
]

const PRIORITY_OPTIONS = [
  { value: '', label: 'All Priorities' },
  { value: 'low', label: 'Low' },
  { value: 'medium', label: 'Medium' },
  { value: 'high', label: 'High' },
  { value: 'urgent', label: 'Urgent' },
]

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = { per_page: 200 }
    if (filters.assigned_to) params.assigned_to = filters.assigned_to
    if (filters.type) params.type = filters.type
    if (filters.priority) params.priority = filters.priority
    const res = await api.get<{ data: Task[] }>('/tasks', { params })
    tasks.value = res.data.data ?? []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
  const ur = await fetchUsers()
  users.value = ur.data ?? []
})

const todo = computed(() => tasks.value.filter(t => t.status === 'todo'))
const inProgress = computed(() => tasks.value.filter(t => t.status === 'in_progress'))
const done = computed(() => tasks.value.filter(t => t.status === 'done'))

function isOverdue(task: Task) {
  if (!task.due_date) return false
  return new Date(task.due_date) < new Date()
}

function initials(name: string) {
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
}

const PRIORITY_COLORS: Record<string, string> = {
  low: '#94a3b8',
  medium: '#3b82f6',
  high: '#f59e0b',
  urgent: '#ef4444',
}

function priorityColor(p: string) {
  return PRIORITY_COLORS[p] ?? '#94a3b8'
}

// Drag & Drop
const draggingId = ref<number | null>(null)

function onDragStart(task: Task) {
  draggingId.value = task.id
}

async function onDrop(newStatus: string) {
  const id = draggingId.value
  if (id === null) return
  draggingId.value = null
  const task = tasks.value.find(t => t.id === id)
  if (!task || task.status === newStatus) return
  task.status = newStatus
  try {
    await api.put('/tasks/' + id + '/status', { status: newStatus })
  } catch {
    await load()
  }
}

// Task Detail Modal
const showDetailModal = ref(false)
const selectedTask = ref<Task | null>(null)
function openDetail(t: Task) { selectedTask.value = t; showDetailModal.value = true }

// Create Modal
const showCreateModal = ref(false)
const creating = ref(false)
const cForm = reactive({
  park_id: null as number | null,
  type: 'general',
  title: '',
  description: '' as string | null,
  assigned_to: null as number | null,
  due_date: '',
  priority: 'medium',
})

const TYPE_CREATE_OPTIONS = TYPE_OPTIONS.slice(1)
const PRIORITY_CREATE_OPTIONS = PRIORITY_OPTIONS.slice(1)

const userOptions = computed(() => users.value.map(u => ({ value: String(u.id), label: u.name })))
const parkOptions = computed(() => parks.value.map(p => ({ value: String(p.id), label: p.name })))

async function submitCreate() {
  if (!cForm.park_id || !cForm.title) return
  creating.value = true
  try {
    await api.post('/tasks', {
      park_id: cForm.park_id,
      type: cForm.type,
      title: cForm.title,
      description: cForm.description || null,
      assigned_to: cForm.assigned_to || null,
      due_date: cForm.due_date || null,
      priority: cForm.priority,
    })
    showCreateModal.value = false
    await load()
  } finally {
    creating.value = false
  }
}

const isAdmin = computed(() => !['park_worker'].includes(auth.role ?? ''))
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Task Board</h2>
      <AppButton @click="showCreateModal = true">+ New Task</AppButton>
    </div>

    <div class="filters">
      <select v-if="isAdmin" v-model="filters.assigned_to" class="filter-sel" @change="load()">
        <option value="">All Assignees</option>
        <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
      </select>
      <select v-model="filters.type" class="filter-sel" @change="load()">
        <option v-for="opt in TYPE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <select v-model="filters.priority" class="filter-sel" @change="load()">
        <option v-for="opt in PRIORITY_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <div class="board">
      <!-- Todo Column -->
      <div
        class="column"
        @dragover.prevent
        @drop="onDrop('todo')"
      >
        <div class="col-header">
          <h3 class="col-title">Todo</h3>
          <span class="col-count">{{ todo.length }}</span>
        </div>
        <div class="cards">
          <div
            v-for="task in todo"
            :key="task.id"
            class="task-card"
            draggable="true"
            @dragstart="onDragStart(task)"
            @click="openDetail(task)"
          >
            <div class="card-header">
              <span class="type-badge">{{ task.type }}</span>
              <span class="priority-dot" :style="{ background: priorityColor(task.priority) }" :title="task.priority"></span>
            </div>
            <div class="card-title">{{ task.title }}</div>
            <div class="card-footer">
              <span v-if="task.assigned_to" class="avatar" :title="task.assigned_to.name">
                {{ initials(task.assigned_to.name) }}
              </span>
              <span v-if="task.due_date" :class="['due-date', { overdue: isOverdue(task) }]">
                {{ task.due_date }}
              </span>
            </div>
          </div>
          <div v-if="todo.length === 0" class="empty-col">No tasks</div>
        </div>
      </div>

      <!-- In Progress Column -->
      <div
        class="column"
        @dragover.prevent
        @drop="onDrop('in_progress')"
      >
        <div class="col-header">
          <h3 class="col-title">In Progress</h3>
          <span class="col-count">{{ inProgress.length }}</span>
        </div>
        <div class="cards">
          <div
            v-for="task in inProgress"
            :key="task.id"
            class="task-card in-progress"
            draggable="true"
            @dragstart="onDragStart(task)"
            @click="openDetail(task)"
          >
            <div class="card-header">
              <span class="type-badge">{{ task.type }}</span>
              <span class="priority-dot" :style="{ background: priorityColor(task.priority) }" :title="task.priority"></span>
            </div>
            <div class="card-title">{{ task.title }}</div>
            <div class="card-footer">
              <span v-if="task.assigned_to" class="avatar" :title="task.assigned_to.name">
                {{ initials(task.assigned_to.name) }}
              </span>
              <span v-if="task.due_date" :class="['due-date', { overdue: isOverdue(task) }]">
                {{ task.due_date }}
              </span>
            </div>
          </div>
          <div v-if="inProgress.length === 0" class="empty-col">No tasks</div>
        </div>
      </div>

      <!-- Done Column -->
      <div
        class="column done-col"
        @dragover.prevent
        @drop="onDrop('done')"
      >
        <div class="col-header">
          <h3 class="col-title">Done</h3>
          <span class="col-count">{{ done.length }}</span>
        </div>
        <div class="cards">
          <div
            v-for="task in done"
            :key="task.id"
            class="task-card done-card"
            draggable="true"
            @dragstart="onDragStart(task)"
            @click="openDetail(task)"
          >
            <div class="card-header">
              <span class="type-badge">{{ task.type }}</span>
              <span class="priority-dot" :style="{ background: priorityColor(task.priority) }" :title="task.priority"></span>
            </div>
            <div class="card-title done-title">{{ task.title }}</div>
            <div class="card-footer">
              <span v-if="task.assigned_to" class="avatar" :title="task.assigned_to.name">
                {{ initials(task.assigned_to.name) }}
              </span>
              <span v-if="task.due_date" :class="['due-date', { overdue: isOverdue(task) }]">
                {{ task.due_date }}
              </span>
            </div>
          </div>
          <div v-if="done.length === 0" class="empty-col">No tasks</div>
        </div>
      </div>
    </div>

    <!-- Task Detail Modal -->
    <AppModal v-model="showDetailModal" :title="selectedTask?.title ?? 'Task'">
      <div class="detail-content" v-if="selectedTask">
        <div class="detail-badges">
          <span class="type-badge lg">{{ selectedTask.type }}</span>
          <span class="priority-badge" :style="{ borderColor: priorityColor(selectedTask.priority), color: priorityColor(selectedTask.priority) }">
            {{ selectedTask.priority }}
          </span>
        </div>
        <div class="detail-grid">
          <div class="info-label">Status</div>
          <div class="info-value"><span class="status-text">{{ selectedTask.status.replace('_', ' ') }}</span></div>
          <div class="info-label">Assigned To</div>
          <div class="info-value">{{ selectedTask.assigned_to?.name ?? 'Unassigned' }}</div>
          <div class="info-label">Park</div>
          <div class="info-value">{{ selectedTask.park?.name ?? '–' }}</div>
          <div class="info-label">Due Date</div>
          <div class="info-value" :class="{ 'text-red': selectedTask.due_date && isOverdue(selectedTask) }">
            {{ selectedTask.due_date ?? '–' }}
          </div>
          <div v-if="selectedTask.description" class="info-label">Description</div>
          <div v-if="selectedTask.description" class="info-value desc">{{ selectedTask.description }}</div>
        </div>
      </div>
      <template #footer>
        <AppButton @click="showDetailModal = false">Close</AppButton>
      </template>
    </AppModal>

    <!-- Create Task Modal -->
    <AppModal v-model="showCreateModal" title="New Task">
      <div class="modal-form">
        <FormInput label="Title *" :model-value="cForm.title" @update:model-value="cForm.title = $event" required />
        <div class="row-2">
          <FormSelect
            label="Type *"
            :model-value="cForm.type"
            @update:model-value="cForm.type = $event"
            :options="TYPE_CREATE_OPTIONS"
          />
          <FormSelect
            label="Priority"
            :model-value="cForm.priority"
            @update:model-value="cForm.priority = $event"
            :options="PRIORITY_CREATE_OPTIONS"
          />
        </div>
        <div>
          <label class="field-label">Park *</label>
          <select v-model="cForm.park_id" class="filter-sel full-w">
            <option :value="null">Select park…</option>
            <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="field-label">Assign To</label>
          <select v-model="cForm.assigned_to" class="filter-sel full-w">
            <option :value="null">Unassigned</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <FormInput label="Due Date" type="date" :model-value="cForm.due_date" @update:model-value="cForm.due_date = $event" />
        <FormTextarea label="Description" :model-value="cForm.description" @update:model-value="cForm.description = $event" :rows="3" />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showCreateModal = false">Cancel</AppButton>
        <AppButton :loading="creating" :disabled="!cForm.park_id || !cForm.title" @click="submitCreate">Create</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; height: 100%; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }

.board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; flex: 1; }

.column { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; display: flex; flex-direction: column; min-height: 400px; }
.col-header { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; }
.col-title { margin: 0; font-size: 0.875rem; font-weight: 600; color: #374151; }
.col-count { background: #e2e8f0; border-radius: 10px; padding: 0.1rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: #64748b; }
.cards { padding: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem; flex: 1; }

.task-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; cursor: grab; transition: box-shadow 0.1s; user-select: none; }
.task-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
.task-card:active { cursor: grabbing; }
.in-progress { border-left: 3px solid #3b82f6; }
.done-card { opacity: 0.6; }

.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.375rem; }
.type-badge { background: #f1f5f9; border-radius: 4px; padding: 0.1rem 0.4rem; font-size: 0.7rem; color: #64748b; text-transform: capitalize; }
.type-badge.lg { font-size: 0.8rem; padding: 0.15rem 0.5rem; }
.priority-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.card-title { font-size: 0.875rem; font-weight: 500; color: #1e293b; line-height: 1.4; }
.done-title { text-decoration: line-through; color: #94a3b8; }

.card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem; }
.avatar { width: 22px; height: 22px; border-radius: 50%; background: #3b82f6; color: #fff; font-size: 0.65rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.due-date { font-size: 0.75rem; color: #64748b; }
.due-date.overdue { color: #ef4444; font-weight: 600; }

.empty-col { text-align: center; color: #94a3b8; font-size: 0.8rem; padding: 1rem 0; }

.detail-content { min-width: 360px; }
.detail-badges { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.priority-badge { border: 1px solid; border-radius: 4px; padding: 0.15rem 0.5rem; font-size: 0.8rem; font-weight: 500; text-transform: capitalize; }
.detail-grid { display: grid; grid-template-columns: 110px 1fr; gap: 0.5rem 1rem; font-size: 0.875rem; }
.info-label { color: #64748b; }
.info-value { font-weight: 500; color: #1e293b; }
.info-value.desc { white-space: pre-wrap; }
.text-red { color: #ef4444 !important; }
.status-text { text-transform: capitalize; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 420px; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; display: block; margin-bottom: 0.25rem; }
.full-w { width: 100%; box-sizing: border-box; }
</style>
