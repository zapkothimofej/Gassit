<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '../../components/AppButton.vue'
import AppModal from '../../components/AppModal.vue'
import AppTable from '../../components/AppTable.vue'
import FormInput from '../../components/FormInput.vue'
import api from '../../api/axios'
import { fetchParks } from '../../api/parks'

const router = useRouter()

interface ElectricityPricing {
  id: number
  price_per_kwh: string
  valid_from: string
  valid_to: string | null
}

interface Meter {
  id: number
  meter_number: string
  meter_type: string
  unit_id: number
  latest_reading: string | null
}

interface UnitWithMeters {
  id: number
  unit_number: string
  meters: Meter[]
}

const parks = ref<Array<{ id: number; name: string }>>([])
const selectedParkId = ref<number | null>(null)
const pricing = ref<ElectricityPricing[]>([])
const units = ref<UnitWithMeters[]>([])
const loadingPricing = ref(false)
const loadingUnits = ref(false)
const toast = ref('')

function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

onMounted(async () => {
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
  const firstPark = parks.value[0]
  if (firstPark) {
    selectedParkId.value = firstPark.id
    await loadAll()
  }
})

watch(selectedParkId, () => { if (selectedParkId.value) loadAll() })

async function loadAll() {
  await Promise.allSettled([loadPricing(), loadUnitsWithMeters()])
}

async function loadPricing() {
  if (!selectedParkId.value) return
  loadingPricing.value = true
  try {
    const res = await api.get<ElectricityPricing[]>('/parks/' + selectedParkId.value + '/electricity-pricing')
    pricing.value = Array.isArray(res.data) ? res.data : []
  } finally {
    loadingPricing.value = false
  }
}

async function loadUnitsWithMeters() {
  if (!selectedParkId.value) return
  loadingUnits.value = true
  try {
    const res = await api.get<{ data: Array<{ id: number; unit_number: string }> }>(
      '/parks/' + selectedParkId.value + '/units',
      { params: { per_page: 100 } },
    )
    const rawUnits = res.data.data ?? []
    const withMeters = await Promise.allSettled(
      rawUnits.map(async (u) => {
        try {
          const mRes = await api.get<Meter[]>('/units/' + u.id + '/meters')
          return { ...u, meters: Array.isArray(mRes.data) ? mRes.data : [] }
        } catch {
          return { ...u, meters: [] }
        }
      }),
    )
    units.value = withMeters
      .filter(r => r.status === 'fulfilled')
      .map(r => (r as PromiseFulfilledResult<UnitWithMeters>).value)
  } finally {
    loadingUnits.value = false
  }
}

const pricingColumns = [
  { key: 'price_per_kwh', label: 'Price/kWh (€)', sortable: false },
  { key: 'valid_from', label: 'Valid From', sortable: false },
  { key: 'valid_to', label: 'Valid To', sortable: false },
]

// Add Pricing Modal
const showPricingModal = ref(false)
const priceForm = ref('')
const addingPrice = ref(false)

async function submitPricing() {
  if (!selectedParkId.value || !priceForm.value) return
  addingPrice.value = true
  try {
    await api.post('/parks/' + selectedParkId.value + '/electricity-pricing', {
      price_per_kwh: Number(priceForm.value),
    })
    showPricingModal.value = false
    priceForm.value = ''
    await loadPricing()
    showToast('New pricing period added.')
  } finally {
    addingPrice.value = false
  }
}

const unitColumns = [
  { key: 'unit_number', label: 'Unit', sortable: false },
  { key: 'meter_count', label: 'Meters', sortable: false },
  { key: 'latest_reading', label: 'Latest Reading', sortable: false },
]
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Electricity</h2>
      <select v-if="parks.length > 1" v-model="selectedParkId" class="park-sel">
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <div v-if="toast" class="toast">{{ toast }}</div>

    <!-- Pricing Section -->
    <div class="card">
      <div class="section-header">
        <h3 class="section-title">Electricity Pricing</h3>
        <AppButton size="sm" @click="showPricingModal = true">+ New Pricing Period</AppButton>
      </div>
      <div v-if="loadingPricing" class="loading">Loading...</div>
      <div v-else-if="pricing.length === 0" class="empty-text">No pricing history.</div>
      <AppTable v-else :columns="pricingColumns" :rows="(pricing as unknown as Record<string, unknown>[])">
        <template #cell-price_per_kwh="{ row }">
          <strong>{{ (row as unknown as ElectricityPricing).price_per_kwh }} €</strong>
        </template>
        <template #cell-valid_to="{ row }">
          <span :class="{ 'current': !(row as unknown as ElectricityPricing).valid_to }">
            {{ (row as unknown as ElectricityPricing).valid_to ?? 'Current' }}
          </span>
        </template>
        <template #empty>No pricing data.</template>
      </AppTable>
    </div>

    <!-- Units & Meters Section -->
    <div class="card">
      <h3 class="section-title">Units & Meters Overview</h3>
      <div v-if="loadingUnits" class="loading">Loading units...</div>
      <div v-else-if="units.length === 0" class="empty-text">No units found.</div>
      <AppTable
        v-else
        :columns="unitColumns"
        :rows="(units as unknown as Record<string, unknown>[])"
        @row-click="(row) => router.push('/units/' + (row as unknown as UnitWithMeters).id + '?tab=electricity')"
      >
        <template #cell-unit_number="{ row }">
          <a class="link">{{ (row as unknown as UnitWithMeters).unit_number }}</a>
        </template>
        <template #cell-meter_count="{ row }">
          {{ (row as unknown as UnitWithMeters).meters.length }}
        </template>
        <template #cell-latest_reading="{ row }">
          {{ (row as unknown as UnitWithMeters).meters[0]?.latest_reading ?? '–' }}
        </template>
        <template #empty>No units.</template>
      </AppTable>
    </div>

    <!-- Add Pricing Modal -->
    <AppModal v-model="showPricingModal" title="New Pricing Period">
      <div class="modal-form">
        <FormInput
          label="Price per kWh (€) *"
          type="number"
          step="0.001"
          :model-value="priceForm"
          @update:model-value="priceForm = $event"
          required
        />
        <p class="note">The current open period will be automatically closed as of today.</p>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showPricingModal = false">Cancel</AppButton>
        <AppButton :loading="addingPrice" :disabled="!priceForm" @click="submitPricing">Add Pricing</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.park-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.toast { background: #22c55e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 6px; text-align: center; }
.loading { color: #64748b; font-size: 0.875rem; padding: 0.5rem 0; }
.empty-text { color: #94a3b8; font-size: 0.875rem; }

.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.section-header { display: flex; justify-content: space-between; align-items: center; }
.section-title { margin: 0; font-size: 1rem; font-weight: 600; }

.current { color: #15803d; font-weight: 600; }
.link { color: #3b82f6; cursor: pointer; }
.link:hover { text-decoration: underline; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 320px; }
.note { font-size: 0.8rem; color: #64748b; margin: 0; }
</style>
