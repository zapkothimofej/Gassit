<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppButton from '../components/AppButton.vue'
import StatusBadge from '../components/StatusBadge.vue'
import FormInput from '../components/FormInput.vue'
import AppModal from '../components/AppModal.vue'
import api from '../api/axios'

const route = useRoute()
const router = useRouter()
const unitId = Number(route.params.id)

// ── Types ────────────────────────────────────────────────────────────────────

interface UnitType {
  id: number
  name: string
}

interface Unit {
  id: number
  unit_number: string
  status: string
  floor: number | null
  building: string | null
  size_m2: number | null
  notes: string | null
  unit_type: UnitType | null
}

interface Contract {
  id: number
  tenant: { first_name: string; last_name: string } | null
  rent_amount: number
  start_date: string
  terminated_at: string | null
}

interface Photo {
  id: number
  url: string
}

interface Meter {
  id: number
  serial_number: string
  readings: Reading[]
  loadingReadings?: boolean
}

interface Reading {
  id: number
  reading_date: string
  meter_value: number
}

interface DamageReport {
  id: number
  title: string
  status: string
  created_at: string
}

// ── State ─────────────────────────────────────────────────────────────────────

const unit = ref<Unit | null>(null)
const loading = ref(true)
const activeTab = ref<'info' | 'photos' | 'contract' | 'history' | 'electricity' | 'damage'>('info')

const STATUS_OPTIONS = ['free', 'reserved', 'active', 'in_repair']

// Status change
const pendingStatus = ref('')
const savingStatus = ref(false)

async function confirmStatusChange() {
  if (!pendingStatus.value || !unit.value) return
  savingStatus.value = true
  try {
    const res = await api.put(`/units/${unitId}/status`, { status: pendingStatus.value })
    unit.value = res.data.data ?? res.data
    pendingStatus.value = ''
  } finally {
    savingStatus.value = false
  }
}

// ── Info tab ──────────────────────────────────────────────────────────────────

const infoForm = reactive({
  unit_number: '',
  floor: null as number | null,
  building: '',
  size_m2: null as number | null,
  notes: '',
})
const savingInfo = ref(false)

function populateInfoForm(u: Unit) {
  infoForm.unit_number = u.unit_number
  infoForm.floor = u.floor
  infoForm.building = u.building ?? ''
  infoForm.size_m2 = u.size_m2
  infoForm.notes = u.notes ?? ''
}

async function saveInfo() {
  savingInfo.value = true
  try {
    const res = await api.put(`/units/${unitId}`, {
      unit_number: infoForm.unit_number,
      floor: infoForm.floor,
      building: infoForm.building || null,
      size_m2: infoForm.size_m2,
      notes: infoForm.notes || null,
    })
    unit.value = res.data.data ?? res.data
  } finally {
    savingInfo.value = false
  }
}

// ── Photos tab ────────────────────────────────────────────────────────────────

const photos = ref<Photo[]>([])
const loadingPhotos = ref(false)
const uploadingPhotos = ref(false)
const photoToDelete = ref<Photo | null>(null)
const deletingPhoto = ref(false)

async function loadPhotos() {
  loadingPhotos.value = true
  try {
    const res = await api.get(`/units/${unitId}/photos`)
    photos.value = res.data.data ?? res.data ?? []
  } finally {
    loadingPhotos.value = false
  }
}

async function uploadPhotos(event: Event) {
  const input = event.target as HTMLInputElement
  if (!input.files?.length) return
  const formData = new FormData()
  for (const file of Array.from(input.files)) {
    formData.append('photos[]', file)
  }
  uploadingPhotos.value = true
  try {
    await api.post(`/units/${unitId}/photos`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await loadPhotos()
  } finally {
    uploadingPhotos.value = false
    input.value = ''
  }
}

async function confirmDeletePhoto() {
  if (!photoToDelete.value) return
  deletingPhoto.value = true
  try {
    await api.delete(`/units/${unitId}/photos/${photoToDelete.value.id}`)
    photos.value = photos.value.filter((p) => p.id !== photoToDelete.value!.id)
    photoToDelete.value = null
  } finally {
    deletingPhoto.value = false
  }
}

// ── Contract tab ──────────────────────────────────────────────────────────────

const activeContract = ref<Contract | null>(null)
const loadingContract = ref(false)

async function loadActiveContract() {
  loadingContract.value = true
  try {
    const res = await api.get(`/contracts`, { params: { unit_id: unitId, status: 'active', per_page: 5 } })
    const list: Contract[] = res.data.data ?? res.data ?? []
    activeContract.value = list[0] ?? null
  } finally {
    loadingContract.value = false
  }
}

// ── History tab ───────────────────────────────────────────────────────────────

const history = ref<Contract[]>([])
const loadingHistory = ref(false)

async function loadHistory() {
  loadingHistory.value = true
  try {
    const res = await api.get(`/units/${unitId}/history`)
    history.value = res.data.data ?? res.data ?? []
  } finally {
    loadingHistory.value = false
  }
}

// ── Electricity tab ───────────────────────────────────────────────────────────

const meters = ref<Meter[]>([])
const loadingMeters = ref(false)
const addingMeter = ref(false)
const showAddMeterModal = ref(false)
const newMeterSerial = ref('')

const showAddReadingModal = ref(false)
const addingReading = ref(false)
const selectedMeterId = ref<number | null>(null)
const readingForm = reactive({ reading_date: '', meter_value: '' })
const readingPhotoFile = ref<File | null>(null)

async function loadMeters() {
  loadingMeters.value = true
  try {
    const res = await api.get(`/units/${unitId}/meters`)
    const list: Meter[] = (res.data.data ?? res.data ?? []).map((m: Meter) => ({ ...m, readings: [], loadingReadings: false }))
    meters.value = list
    await Promise.all(list.map((m) => loadReadings(m)))
  } finally {
    loadingMeters.value = false
  }
}

async function loadReadings(meter: Meter) {
  meter.loadingReadings = true
  try {
    const res = await api.get(`/meters/${meter.id}/readings`)
    meter.readings = res.data.data ?? res.data ?? []
  } finally {
    meter.loadingReadings = false
  }
}

async function addMeter() {
  if (!newMeterSerial.value) return
  addingMeter.value = true
  try {
    await api.post(`/units/${unitId}/meters`, { serial_number: newMeterSerial.value })
    showAddMeterModal.value = false
    newMeterSerial.value = ''
    await loadMeters()
  } finally {
    addingMeter.value = false
  }
}

function openAddReading(meterId: number) {
  selectedMeterId.value = meterId
  readingForm.reading_date = ''
  readingForm.meter_value = ''
  readingPhotoFile.value = null
  showAddReadingModal.value = true
}

async function addReading() {
  if (!selectedMeterId.value || !readingForm.reading_date || !readingForm.meter_value) return
  addingReading.value = true
  try {
    const formData = new FormData()
    formData.append('reading_date', readingForm.reading_date)
    formData.append('meter_value', readingForm.meter_value)
    if (readingPhotoFile.value) formData.append('photo', readingPhotoFile.value)
    await api.post(`/meters/${selectedMeterId.value}/readings`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    showAddReadingModal.value = false
    const meter = meters.value.find((m) => m.id === selectedMeterId.value)
    if (meter) await loadReadings(meter)
  } finally {
    addingReading.value = false
  }
}

// ── Damage tab ────────────────────────────────────────────────────────────────

const damageReports = ref<DamageReport[]>([])
const loadingDamage = ref(false)

async function loadDamage() {
  loadingDamage.value = true
  try {
    const res = await api.get('/api/damage-reports', { params: { unit_id: unitId } })
    damageReports.value = res.data.data ?? res.data ?? []
  } finally {
    loadingDamage.value = false
  }
}

// ── Tab switching ─────────────────────────────────────────────────────────────

const tabLoaded = new Set<string>()

async function switchTab(tab: typeof activeTab.value) {
  activeTab.value = tab
  if (tabLoaded.has(tab)) return
  tabLoaded.add(tab)
  if (tab === 'photos') await loadPhotos()
  if (tab === 'contract') await loadActiveContract()
  if (tab === 'history') await loadHistory()
  if (tab === 'electricity') await loadMeters()
  if (tab === 'damage') await loadDamage()
}

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(async () => {
  try {
    const res = await api.get(`/units/${unitId}`)
    unit.value = res.data.data ?? res.data
    if (unit.value) populateInfoForm(unit.value)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page">
    <div v-if="loading" class="loading-hint">Loading...</div>

    <template v-else-if="unit">
      <!-- Header -->
      <div class="page-header">
        <div class="header-left">
          <button class="back-btn" @click="router.back()">← Back</button>
          <h2 class="unit-title">{{ unit.unit_number }}</h2>
          <span v-if="unit.unit_type" class="type-badge">{{ unit.unit_type.name }}</span>
          <StatusBadge :status="unit.status" />
        </div>
        <div class="status-change">
          <select v-model="pendingStatus" class="filter-select">
            <option value="">Change status...</option>
            <option v-for="s in STATUS_OPTIONS" :key="s" :value="s">{{ s.replace(/_/g, ' ') }}</option>
          </select>
          <AppButton
            size="sm"
            :disabled="!pendingStatus"
            :loading="savingStatus"
            @click="confirmStatusChange"
          >
            Confirm
          </AppButton>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button
          v-for="tab in ['info', 'photos', 'contract', 'history', 'electricity', 'damage'] as const"
          :key="tab"
          :class="['tab-btn', { active: activeTab === tab }]"
          @click="switchTab(tab)"
        >
          {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
        </button>
      </div>

      <!-- Info Tab -->
      <div v-if="activeTab === 'info'" class="tab-content">
        <div class="form-grid">
          <FormInput
            label="Unit Number"
            :model-value="infoForm.unit_number"
            required
            @update:model-value="infoForm.unit_number = $event"
          />
          <FormInput
            label="Floor"
            type="number"
            :model-value="infoForm.floor"
            @update:model-value="infoForm.floor = $event ? Number($event) : null"
          />
          <FormInput
            label="Building"
            :model-value="infoForm.building"
            @update:model-value="infoForm.building = $event"
          />
          <FormInput
            label="Size (m²)"
            type="number"
            :model-value="infoForm.size_m2"
            @update:model-value="infoForm.size_m2 = $event ? Number($event) : null"
          />
          <div class="span-2">
            <label class="field-label">Notes</label>
            <textarea
              class="form-textarea"
              :value="infoForm.notes"
              rows="4"
              @input="infoForm.notes = ($event.target as HTMLTextAreaElement).value"
            />
          </div>
        </div>
        <div class="form-actions">
          <AppButton :loading="savingInfo" @click="saveInfo">Save</AppButton>
        </div>
      </div>

      <!-- Photos Tab -->
      <div v-else-if="activeTab === 'photos'" class="tab-content">
        <div class="tab-toolbar">
          <label class="upload-label">
            <AppButton tag="span" :loading="uploadingPhotos">Upload Photos</AppButton>
            <input type="file" multiple accept="image/*" class="hidden-input" @change="uploadPhotos" />
          </label>
        </div>
        <div v-if="loadingPhotos" class="loading-hint">Loading photos...</div>
        <div v-else-if="photos.length === 0" class="empty-hint">No photos yet.</div>
        <div v-else class="photo-grid">
          <div v-for="photo in photos" :key="photo.id" class="photo-card">
            <img :src="photo.url" class="photo-img" />
            <AppButton
              variant="danger"
              size="sm"
              class="photo-delete"
              @click="photoToDelete = photo"
            >
              Delete
            </AppButton>
          </div>
        </div>
      </div>

      <!-- Contract Tab -->
      <div v-else-if="activeTab === 'contract'" class="tab-content">
        <div v-if="loadingContract" class="loading-hint">Loading...</div>
        <div v-else-if="!activeContract" class="empty-hint">
          No active contract. <router-link to="/contracts">View all contracts</router-link>
        </div>
        <div v-else class="info-card">
          <div class="info-row">
            <span class="info-label">Tenant</span>
            <span>{{ activeContract.tenant ? activeContract.tenant.first_name + ' ' + activeContract.tenant.last_name : '—' }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Rent</span>
            <span>{{ activeContract.rent_amount }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Start Date</span>
            <span>{{ activeContract.start_date }}</span>
          </div>
          <div class="info-row">
            <span class="info-label"></span>
            <router-link to="/contracts">View all contracts →</router-link>
          </div>
        </div>
      </div>

      <!-- History Tab -->
      <div v-else-if="activeTab === 'history'" class="tab-content">
        <div v-if="loadingHistory" class="loading-hint">Loading...</div>
        <div v-else-if="history.length === 0" class="empty-hint">No contract history.</div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Tenant</th>
              <th>Start Date</th>
              <th>Terminated At</th>
              <th>Rent Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in history" :key="c.id">
              <td>{{ c.tenant ? c.tenant.first_name + ' ' + c.tenant.last_name : '—' }}</td>
              <td>{{ c.start_date }}</td>
              <td>{{ c.terminated_at ?? '—' }}</td>
              <td>{{ c.rent_amount }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Electricity Tab -->
      <div v-else-if="activeTab === 'electricity'" class="tab-content">
        <div class="tab-toolbar">
          <AppButton size="sm" @click="showAddMeterModal = true">+ Add Meter</AppButton>
        </div>
        <div v-if="loadingMeters" class="loading-hint">Loading meters...</div>
        <div v-else-if="meters.length === 0" class="empty-hint">No meters.</div>
        <div v-else class="meters-list">
          <div v-for="meter in meters" :key="meter.id" class="meter-card">
            <div class="meter-header">
              <span class="meter-serial">Meter: {{ meter.serial_number }}</span>
              <AppButton size="sm" variant="secondary" @click="openAddReading(meter.id)">+ Add Reading</AppButton>
            </div>
            <div v-if="meter.loadingReadings" class="loading-hint small">Loading readings...</div>
            <table v-else-if="meter.readings.length" class="data-table inner-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Value</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in meter.readings" :key="r.id">
                  <td>{{ r.reading_date }}</td>
                  <td>{{ r.meter_value }}</td>
                </tr>
              </tbody>
            </table>
            <p v-else class="empty-hint small">No readings yet.</p>
          </div>
        </div>
      </div>

      <!-- Damage Tab -->
      <div v-else-if="activeTab === 'damage'" class="tab-content">
        <div v-if="loadingDamage" class="loading-hint">Loading...</div>
        <div v-else-if="damageReports.length === 0" class="empty-hint">No damage reports.</div>
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in damageReports" :key="d.id">
              <td>{{ d.title }}</td>
              <td><StatusBadge :status="d.status" /></td>
              <td>{{ d.created_at.slice(0, 10) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <div v-else class="empty-hint">Unit not found.</div>

    <!-- Delete Photo Modal -->
    <AppModal v-model="(photoToDelete !== null)" title="Delete Photo">
      <p>Are you sure you want to delete this photo?</p>
      <template #footer>
        <AppButton variant="secondary" @click="photoToDelete = null">Cancel</AppButton>
        <AppButton variant="danger" :loading="deletingPhoto" @click="confirmDeletePhoto">Delete</AppButton>
      </template>
    </AppModal>

    <!-- Add Meter Modal -->
    <AppModal v-model="showAddMeterModal" title="Add Meter">
      <FormInput
        label="Serial Number"
        :model-value="newMeterSerial"
        placeholder="e.g. MTR-001"
        required
        @update:model-value="newMeterSerial = $event"
      />
      <template #footer>
        <AppButton variant="secondary" @click="showAddMeterModal = false">Cancel</AppButton>
        <AppButton :loading="addingMeter" @click="addMeter">Add</AppButton>
      </template>
    </AppModal>

    <!-- Add Reading Modal -->
    <AppModal v-model="showAddReadingModal" title="Add Reading">
      <div class="form-grid single">
        <FormInput
          label="Date"
          type="date"
          :model-value="readingForm.reading_date"
          required
          @update:model-value="readingForm.reading_date = $event"
        />
        <FormInput
          label="Meter Value"
          type="number"
          :model-value="readingForm.meter_value"
          required
          @update:model-value="readingForm.meter_value = $event"
        />
        <div class="form-field">
          <label class="field-label">Photo (optional)</label>
          <input
            type="file"
            accept="image/*"
            @change="readingPhotoFile = ($event.target as HTMLInputElement).files?.[0] ?? null"
          />
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showAddReadingModal = false">Cancel</AppButton>
        <AppButton :loading="addingReading" @click="addReading">Save</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.back-btn {
  background: none;
  border: none;
  color: #3b82f6;
  cursor: pointer;
  font-size: 0.875rem;
  padding: 0;
}

.unit-title { margin: 0; font-size: 1.5rem; }

.type-badge {
  background: #e0e7ff;
  color: #3730a3;
  border-radius: 9999px;
  padding: 0.2rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.status-change {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 0;
  border-bottom: 2px solid #e2e8f0;
}

.tab-btn {
  background: none;
  border: none;
  padding: 0.6rem 1.1rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  transition: color 0.15s, border-color 0.15s;
}

.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; }
.tab-btn:hover:not(.active) { color: #374151; }

/* Tab content */
.tab-content {
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.tab-toolbar { display: flex; gap: 0.75rem; align-items: center; }

/* Info form */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.form-grid.single { grid-template-columns: 1fr; }

.span-2 { grid-column: 1 / -1; display: flex; flex-direction: column; gap: 0.25rem; }

.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; }

.form-textarea {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  outline: none;
  resize: vertical;
  font-family: inherit;
  transition: border-color 0.15s;
}

.form-textarea:focus { border-color: #3b82f6; }

.form-actions { display: flex; justify-content: flex-end; }

.form-field { display: flex; flex-direction: column; gap: 0.25rem; }

/* Photos */
.upload-label { position: relative; cursor: pointer; }
.hidden-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 0.75rem;
}

.photo-card {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

.photo-img {
  width: 100%;
  height: 140px;
  object-fit: cover;
  display: block;
}

.photo-delete {
  position: absolute;
  top: 0.4rem;
  right: 0.4rem;
}

/* Contract info */
.info-card { display: flex; flex-direction: column; gap: 0.75rem; }

.info-row { display: flex; gap: 1rem; align-items: baseline; }

.info-label { font-weight: 600; color: #374151; min-width: 100px; font-size: 0.875rem; }

/* Table */
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

.data-table th {
  text-align: left;
  padding: 0.6rem 0.875rem;
  font-weight: 600;
  color: #374151;
  border-bottom: 1px solid #e2e8f0;
  background: #f8fafc;
}

.data-table td {
  padding: 0.6rem 0.875rem;
  border-bottom: 1px solid #f1f5f9;
  color: #374151;
}

/* Meters */
.meters-list { display: flex; flex-direction: column; gap: 1rem; }

.meter-card {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
}

.meter-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.meter-serial { font-weight: 600; font-size: 0.875rem; }

.inner-table { margin: 0; }

/* Misc */
.loading-hint { color: #94a3b8; font-size: 0.875rem; padding: 1rem 0; }
.loading-hint.small { padding: 0.5rem 1rem; }

.empty-hint { color: #94a3b8; font-size: 0.875rem; text-align: center; padding: 2rem 0; }
.empty-hint.small { padding: 0.5rem 1rem; text-align: left; }

.filter-select {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.4rem 0.75rem;
  font-size: 0.875rem;
  background: #fff;
}
</style>
