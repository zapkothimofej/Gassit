<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppTable from '../components/AppTable.vue'
import AppPagination from '../components/AppPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import AppButton from '../components/AppButton.vue'
import { fetchContracts, exportContracts, type Contract } from '../api/contracts'
import { fetchParks } from '../api/parks'

const router = useRouter()

const filters = reactive({
  search: '',
  park_id: null as number | null,
  status: '',
  start_date_from: '',
  start_date_to: '',
  page: 1,
})

const contracts = ref<Contract[]>([])
const totalPages = ref(1)
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'draft', label: 'Draft' },
  { value: 'pending_signature', label: 'Pending Signature' },
  { value: 'active', label: 'Active' },
  { value: 'expired', label: 'Expired' },
  { value: 'terminated', label: 'Terminated' },
]

async function load() {
  loading.value = true
  try {
    const res = await fetchContracts({
      search: filters.search || undefined,
      park_id: filters.park_id || undefined,
      status: filters.status || undefined,
      start_date_from: filters.start_date_from || undefined,
      start_date_to: filters.start_date_to || undefined,
      page: filters.page,
      per_page: 20,
    })
    contracts.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

watch(
  () => [filters.search, filters.park_id, filters.status, filters.start_date_from, filters.start_date_to],
  () => { filters.page = 1; load() },
)

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

const columns = [
  { key: 'contract_number', label: 'Contract ID', sortable: false },
  { key: 'customer', label: 'Customer', sortable: false },
  { key: 'unit', label: 'Unit', sortable: false },
  { key: 'park', label: 'Park', sortable: false },
  { key: 'start_date', label: 'Start Date', sortable: false },
  { key: 'end_date', label: 'End Date', sortable: false },
  { key: 'rent_amount', label: 'Rent', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
]

function customerName(c: Contract['customer']) {
  return c.type === 'company' && c.company_name ? c.company_name : c.first_name + ' ' + c.last_name
}

async function doExport() {
  const res = await exportContracts({
    search: filters.search || undefined,
    park_id: filters.park_id || undefined,
    status: filters.status || undefined,
    start_date_from: filters.start_date_from || undefined,
    start_date_to: filters.start_date_to || undefined,
  })
  const url = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'contracts.xlsx'
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Contracts</h2>
      <AppButton variant="secondary" size="sm" @click="doExport">Export</AppButton>
    </div>

    <div class="filters">
      <input v-model="filters.search" class="search-input" placeholder="Search by customer name or contract number..." />
      <select v-model="filters.park_id" class="filter-sel">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.status" class="filter-sel">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <div class="date-range">
        <label class="date-label">Start:</label>
        <input v-model="filters.start_date_from" type="date" class="filter-sel" />
        <span class="date-sep">–</span>
        <input v-model="filters.start_date_to" type="date" class="filter-sel" />
      </div>
    </div>

    <AppTable
      :columns="columns"
      :rows="(contracts as unknown as Record<string, unknown>[])"
      @row-click="(row) => router.push('/contracts/' + (row as unknown as Contract).id)"
    >
      <template #cell-contract_number="{ row }">
        <span class="contract-id">{{ (row as unknown as Contract).contract_number }}</span>
      </template>
      <template #cell-customer="{ row }">
        {{ customerName((row as unknown as Contract).customer) }}
      </template>
      <template #cell-unit="{ row }">
        {{ (row as unknown as Contract).unit?.unit_number ?? '–' }}
      </template>
      <template #cell-park="{ row }">
        {{ (row as unknown as Contract).park?.name ?? '–' }}
      </template>
      <template #cell-start_date="{ row }">
        {{ (row as unknown as Contract).start_date }}
      </template>
      <template #cell-end_date="{ row }">
        {{ (row as unknown as Contract).end_date ?? '–' }}
      </template>
      <template #cell-rent_amount="{ row }">
        {{ (row as unknown as Contract).rent_amount }} €
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="(row as unknown as Contract).status" />
      </template>
      <template #empty>No contracts found.</template>
    </AppTable>

    <AppPagination
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p) => { filters.page = p; load() }"
    />
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.search-input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; min-width: 280px; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }

.date-range { display: flex; align-items: center; gap: 0.375rem; }
.date-label { font-size: 0.8rem; color: #64748b; white-space: nowrap; }
.date-sep { color: #94a3b8; }

.contract-id { font-family: monospace; font-size: 0.85rem; color: #1d4ed8; }
</style>
