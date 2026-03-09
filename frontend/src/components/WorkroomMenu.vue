<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import AppModal from './AppModal.vue'
import api from '../api/axios'

const auth = useAuthStore()

const menuOpen = ref(false)
const toast = ref('')
const toastTimer = ref<ReturnType<typeof setTimeout> | null>(null)

function showToast(msg: string) {
  if (toastTimer.value) clearTimeout(toastTimer.value)
  toast.value = msg
  toastTimer.value = setTimeout(() => { toast.value = '' }, 3000)
}

function defaultParkId(): number | null {
  return auth.parks[0]?.id ?? null
}

// --- Modal state ---
type ModalType = 'application' | 'damage' | 'ticket' | 'invoice' | 'task' | null
const activeModal = ref<ModalType>(null)
const saving = ref(false)

function modalRef(type: Exclude<ModalType, null>) {
  return computed({
    get: () => activeModal.value === type,
    set: (v: boolean) => { if (!v) activeModal.value = null },
  })
}
const showApplicationModal = modalRef('application')
const showDamageModal = modalRef('damage')
const showTicketModal = modalRef('ticket')
const showInvoiceModal = modalRef('invoice')
const showTaskModal = modalRef('task')

function openModal(type: ModalType) {
  menuOpen.value = false
  activeModal.value = type
  resetForms()
}

function closeModal() {
  activeModal.value = null
}

// --- Application form ---
const appForm = ref({
  park_id: null as number | null,
  first_name: '',
  last_name: '',
  email: '',
  desired_start_date: '',
  source: 'walk-in',
})

// --- Damage form ---
const dmgForm = ref({
  park_id: null as number | null,
  unit_number: '',
  description: '',
  reported_at: new Date().toISOString().slice(0, 10),
})

// --- Ticket form ---
const ticketForm = ref({
  park_id: null as number | null,
  title: '',
  body: '',
})

// --- Invoice form ---
const invForm = ref({
  park_id: null as number | null,
  contract_id: '' as string | number,
  description: '',
  amount: '' as string | number,
  due_date: '',
})

// --- Task form ---
const taskForm = ref({
  park_id: null as number | null,
  title: '',
  description: '',
  due_date: '',
  priority: 'medium',
})

function resetForms() {
  const pid = defaultParkId()
  appForm.value = { park_id: pid, first_name: '', last_name: '', email: '', desired_start_date: '', source: 'walk-in' }
  dmgForm.value = { park_id: pid, unit_number: '', description: '', reported_at: new Date().toISOString().slice(0, 10) }
  ticketForm.value = { park_id: pid, title: '', body: '' }
  invForm.value = { park_id: pid, contract_id: '', description: '', amount: '', due_date: '' }
  taskForm.value = { park_id: pid, title: '', description: '', due_date: '', priority: 'medium' }
}

async function saveApplication() {
  saving.value = true
  try {
    await api.post('/applications', {
      park_id: appForm.value.park_id,
      customer: { first_name: appForm.value.first_name, last_name: appForm.value.last_name, email: appForm.value.email },
      desired_start_date: appForm.value.desired_start_date || undefined,
      source: appForm.value.source,
    })
    closeModal()
    showToast('Anfrage erfolgreich erstellt')
  } catch {
    showToast('Fehler beim Erstellen der Anfrage')
  } finally {
    saving.value = false
  }
}

async function saveDamage() {
  saving.value = true
  try {
    await api.post('/damage-reports', {
      park_id: dmgForm.value.park_id,
      unit_number: dmgForm.value.unit_number || undefined,
      description: dmgForm.value.description,
      reported_at: dmgForm.value.reported_at,
    })
    closeModal()
    showToast('Schadensmeldung erfolgreich erstellt')
  } catch {
    showToast('Fehler beim Erstellen der Schadensmeldung')
  } finally {
    saving.value = false
  }
}

async function saveTicket() {
  saving.value = true
  try {
    await api.post('/tickets', {
      park_id: ticketForm.value.park_id,
      title: ticketForm.value.title,
      body: ticketForm.value.body,
    })
    closeModal()
    showToast('Ticket erfolgreich erstellt')
  } catch {
    showToast('Fehler beim Erstellen des Tickets')
  } finally {
    saving.value = false
  }
}

async function saveInvoice() {
  saving.value = true
  try {
    await api.post('/invoices', {
      park_id: invForm.value.park_id,
      contract_id: invForm.value.contract_id || undefined,
      description: invForm.value.description,
      amount: invForm.value.amount,
      due_date: invForm.value.due_date || undefined,
    })
    closeModal()
    showToast('Rechnung erfolgreich erstellt')
  } catch {
    showToast('Fehler beim Erstellen der Rechnung')
  } finally {
    saving.value = false
  }
}

async function saveTask() {
  saving.value = true
  try {
    await api.post('/tasks', {
      park_id: taskForm.value.park_id,
      title: taskForm.value.title,
      description: taskForm.value.description || undefined,
      due_date: taskForm.value.due_date || undefined,
      priority: taskForm.value.priority,
    })
    closeModal()
    showToast('Aufgabe erfolgreich erstellt')
  } catch {
    showToast('Fehler beim Erstellen der Aufgabe')
  } finally {
    saving.value = false
  }
}

// --- Keyboard shortcuts (chord: N then A/S/T/F/U within 800ms) ---
let nPressed = false
let nTimer: ReturnType<typeof setTimeout> | null = null

function handleKeydown(e: KeyboardEvent) {
  const tag = (e.target as HTMLElement).tagName
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return

  if (e.key === 'n' || e.key === 'N') {
    nPressed = true
    if (nTimer) clearTimeout(nTimer)
    nTimer = setTimeout(() => { nPressed = false }, 800)
    return
  }

  if (nPressed) {
    const key = e.key.toLowerCase()
    if (key === 'a') { nPressed = false; openModal('application') }
    else if (key === 's') { nPressed = false; openModal('damage') }
    else if (key === 't') { nPressed = false; openModal('ticket') }
    else if (key === 'f') { nPressed = false; openModal('invoice') }
    else if (key === 'u') { nPressed = false; openModal('task') }
  }
}

function handleOutsideClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.workroom-wrapper')) {
    menuOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.addEventListener('click', handleOutsideClick)
})
onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('click', handleOutsideClick)
  if (nTimer) clearTimeout(nTimer)
  if (toastTimer.value) clearTimeout(toastTimer.value)
})

type NonNullModalType = Exclude<ModalType, null>

const menuItems: Array<{ type: NonNullModalType; label: string; icon: string; shortcut: string }> = [
  { type: 'application', label: 'Neue Anfrage', icon: '📝', shortcut: 'N+A' },
  { type: 'damage',      label: 'Neuer Schaden', icon: '🔧', shortcut: 'N+S' },
  { type: 'ticket',      label: 'Neues Ticket',  icon: '🎫', shortcut: 'N+T' },
  { type: 'invoice',     label: 'Neue Factura',  icon: '🧾', shortcut: 'N+F' },
  { type: 'task',        label: 'Neue Aufgabe',  icon: '✅', shortcut: 'N+U' },
]
</script>

<template>
  <div class="workroom-wrapper">
    <!-- Workroom button -->
    <button
      class="workroom-btn"
      title="Workroom — Neu erstellen (Shortcut: N+A/S/T/F/U)"
      @click.stop="menuOpen = !menuOpen"
    >
      <span class="workroom-icon">＋</span>
      <span class="workroom-label">Workroom</span>
    </button>

    <!-- Dropdown menu -->
    <div v-if="menuOpen" class="workroom-dropdown">
      <div class="workroom-dropdown-header">
        Schnell erstellen
        <span class="shortcut-hint">N+...</span>
      </div>
      <button
        v-for="item in menuItems"
        :key="item.type"
        class="workroom-menu-item"
        @click="openModal(item.type)"
      >
        <span class="menu-icon">{{ item.icon }}</span>
        <span class="menu-label">{{ item.label }}</span>
        <kbd class="menu-kbd">{{ item.shortcut }}</kbd>
      </button>
    </div>

    <!-- Toast -->
    <Teleport to="body">
      <div v-if="toast" class="workroom-toast">{{ toast }}</div>
    </Teleport>

    <!-- Application modal -->
    <AppModal v-model="showApplicationModal" title="Neue Anfrage">
      <div class="form-grid">
        <div class="form-row">
          <label>Park</label>
          <select v-model="appForm.park_id" class="form-ctrl">
            <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="form-row">
          <label>Vorname</label>
          <input v-model="appForm.first_name" class="form-ctrl" type="text" placeholder="Vorname" />
        </div>
        <div class="form-row">
          <label>Nachname</label>
          <input v-model="appForm.last_name" class="form-ctrl" type="text" placeholder="Nachname" />
        </div>
        <div class="form-row">
          <label>E-Mail</label>
          <input v-model="appForm.email" class="form-ctrl" type="email" placeholder="email@beispiel.de" />
        </div>
        <div class="form-row">
          <label>Gewünschter Start</label>
          <input v-model="appForm.desired_start_date" class="form-ctrl" type="date" />
        </div>
        <div class="form-row">
          <label>Quelle</label>
          <select v-model="appForm.source" class="form-ctrl">
            <option value="walk-in">Walk-in</option>
            <option value="phone">Telefon</option>
            <option value="email">E-Mail</option>
            <option value="website">Website</option>
            <option value="referral">Empfehlung</option>
          </select>
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="closeModal">Abbrechen</button>
        <button class="btn-primary" :disabled="saving || !appForm.first_name || !appForm.last_name" @click="saveApplication">
          {{ saving ? 'Speichern...' : 'Anfrage erstellen' }}
        </button>
      </template>
    </AppModal>

    <!-- Damage modal -->
    <AppModal v-model="showDamageModal" title="Neuer Schaden">
      <div class="form-grid">
        <div class="form-row">
          <label>Park</label>
          <select v-model="dmgForm.park_id" class="form-ctrl">
            <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="form-row">
          <label>Einheit (Nr.)</label>
          <input v-model="dmgForm.unit_number" class="form-ctrl" type="text" placeholder="z.B. A-12" />
        </div>
        <div class="form-row">
          <label>Beschreibung</label>
          <textarea v-model="dmgForm.description" class="form-ctrl" rows="3" placeholder="Schadensbeschreibung..." />
        </div>
        <div class="form-row">
          <label>Datum</label>
          <input v-model="dmgForm.reported_at" class="form-ctrl" type="date" />
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="closeModal">Abbrechen</button>
        <button class="btn-primary" :disabled="saving || !dmgForm.description" @click="saveDamage">
          {{ saving ? 'Speichern...' : 'Schaden melden' }}
        </button>
      </template>
    </AppModal>

    <!-- Ticket modal -->
    <AppModal v-model="showTicketModal" title="Neues Ticket">
      <div class="form-grid">
        <div class="form-row">
          <label>Park</label>
          <select v-model="ticketForm.park_id" class="form-ctrl">
            <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="form-row">
          <label>Titel</label>
          <input v-model="ticketForm.title" class="form-ctrl" type="text" placeholder="Ticket-Titel" />
        </div>
        <div class="form-row">
          <label>Beschreibung</label>
          <textarea v-model="ticketForm.body" class="form-ctrl" rows="4" placeholder="Details..." />
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="closeModal">Abbrechen</button>
        <button class="btn-primary" :disabled="saving || !ticketForm.title" @click="saveTicket">
          {{ saving ? 'Speichern...' : 'Ticket erstellen' }}
        </button>
      </template>
    </AppModal>

    <!-- Invoice modal -->
    <AppModal v-model="showInvoiceModal" title="Neue Factura">
      <div class="form-grid">
        <div class="form-row">
          <label>Park</label>
          <select v-model="invForm.park_id" class="form-ctrl">
            <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="form-row">
          <label>Vertrags-ID</label>
          <input v-model="invForm.contract_id" class="form-ctrl" type="number" placeholder="Vertrags-ID" />
        </div>
        <div class="form-row">
          <label>Beschreibung</label>
          <input v-model="invForm.description" class="form-ctrl" type="text" placeholder="Rechnungsbezeichnung" />
        </div>
        <div class="form-row">
          <label>Betrag (€)</label>
          <input v-model="invForm.amount" class="form-ctrl" type="number" step="0.01" placeholder="0.00" />
        </div>
        <div class="form-row">
          <label>Fälligkeitsdatum</label>
          <input v-model="invForm.due_date" class="form-ctrl" type="date" />
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="closeModal">Abbrechen</button>
        <button class="btn-primary" :disabled="saving || !invForm.amount" @click="saveInvoice">
          {{ saving ? 'Speichern...' : 'Factura erstellen' }}
        </button>
      </template>
    </AppModal>

    <!-- Task modal -->
    <AppModal v-model="showTaskModal" title="Neue Aufgabe">
      <div class="form-grid">
        <div class="form-row">
          <label>Park</label>
          <select v-model="taskForm.park_id" class="form-ctrl">
            <option v-for="p in auth.parks" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="form-row">
          <label>Titel</label>
          <input v-model="taskForm.title" class="form-ctrl" type="text" placeholder="Aufgaben-Titel" />
        </div>
        <div class="form-row">
          <label>Beschreibung</label>
          <textarea v-model="taskForm.description" class="form-ctrl" rows="3" placeholder="Details..." />
        </div>
        <div class="form-row">
          <label>Fällig am</label>
          <input v-model="taskForm.due_date" class="form-ctrl" type="date" />
        </div>
        <div class="form-row">
          <label>Priorität</label>
          <select v-model="taskForm.priority" class="form-ctrl">
            <option value="low">Niedrig</option>
            <option value="medium">Mittel</option>
            <option value="high">Hoch</option>
          </select>
        </div>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="closeModal">Abbrechen</button>
        <button class="btn-primary" :disabled="saving || !taskForm.title" @click="saveTask">
          {{ saving ? 'Speichern...' : 'Aufgabe erstellen' }}
        </button>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.workroom-wrapper {
  position: relative;
  padding: 0.75rem;
  border-top: 1px solid #334155;
}

.workroom-btn {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem 0.75rem;
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.875rem;
  font-weight: 600;
  transition: background 0.15s;
}

.workroom-btn:hover {
  background: #2563eb;
}

.workroom-icon {
  font-size: 1.1rem;
  line-height: 1;
  font-style: normal;
}

.workroom-label {
  flex: 1;
  text-align: left;
}

.workroom-dropdown {
  position: absolute;
  bottom: calc(100% - 0.75rem + 4px);
  left: 0.75rem;
  right: 0.75rem;
  background: #1e293b;
  border: 1px solid #475569;
  border-radius: 8px;
  box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.3);
  overflow: hidden;
  z-index: 500;
}

.workroom-dropdown-header {
  padding: 0.5rem 0.875rem;
  font-size: 0.6875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #64748b;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #334155;
}

.shortcut-hint {
  font-size: 0.6rem;
  background: #334155;
  color: #94a3b8;
  border-radius: 3px;
  padding: 1px 4px;
  font-family: monospace;
}

.workroom-menu-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem 0.875rem;
  background: none;
  border: none;
  border-bottom: 1px solid #334155;
  color: #cbd5e1;
  font-size: 0.875rem;
  cursor: pointer;
  text-align: left;
  transition: background 0.1s, color 0.1s;
}

.workroom-menu-item:last-child {
  border-bottom: none;
}

.workroom-menu-item:hover {
  background: #334155;
  color: #f8fafc;
}

.menu-icon {
  font-size: 0.9rem;
  width: 1.25rem;
  text-align: center;
}

.menu-label {
  flex: 1;
}

.menu-kbd {
  font-size: 0.6rem;
  background: #0f172a;
  color: #94a3b8;
  border: 1px solid #475569;
  border-radius: 3px;
  padding: 1px 5px;
  font-family: monospace;
}

/* Form styles */
.form-grid {
  display: flex;
  flex-direction: column;
  gap: 0.875rem;
}

.form-row {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.form-row label {
  font-size: 0.8125rem;
  font-weight: 500;
  color: #374151;
}

.form-ctrl {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  padding: 0.5rem 0.625rem;
  font-size: 0.875rem;
  color: #1e293b;
  outline: none;
  background: #fff;
  box-sizing: border-box;
  transition: border-color 0.15s;
  font-family: inherit;
}

.form-ctrl:focus {
  border-color: #3b82f6;
}

textarea.form-ctrl {
  resize: vertical;
}

.btn-primary {
  background: #3b82f6;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-secondary {
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.5rem 1rem;
  font-size: 0.875rem;
  color: #64748b;
  cursor: pointer;
  transition: background 0.15s;
}

.btn-secondary:hover {
  background: #f1f5f9;
}

/* Global toast */
.workroom-toast {
  position: fixed;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  background: #22c55e;
  color: #fff;
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  z-index: 9999;
  pointer-events: none;
}
</style>
