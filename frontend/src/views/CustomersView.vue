<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppTable from '../components/AppTable.vue'
import AppPagination from '../components/AppPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import AppModal from '../components/AppModal.vue'
import AppButton from '../components/AppButton.vue'
import FormInput from '../components/FormInput.vue'
import { fetchCustomers, createCustomer, exportCustomers, type Customer } from '../api/customers'
import { fetchParks } from '../api/parks'

const router = useRouter()

const filters = reactive({
  search: '',
  park_id: null as number | null,
  status: '',
  type: '',
  page: 1,
})

const customers = ref<Customer[]>([])
const totalPages = ref(1)
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'new', label: 'New' },
  { value: 'tenant', label: 'Tenant' },
  { value: 'not_renting', label: 'Not Renting' },
  { value: 'debtor', label: 'Debtor' },
  { value: 'troublemaker', label: 'Troublemaker' },
  { value: 'blacklisted', label: 'Blacklisted' },
]

const TYPE_OPTIONS = [
  { value: '', label: 'All Types' },
  { value: 'private', label: 'Private' },
  { value: 'company', label: 'Company' },
]

async function load() {
  loading.value = true
  try {
    const res = await fetchCustomers({
      search: filters.search || undefined,
      park_id: filters.park_id || undefined,
      status: filters.status || undefined,
      type: filters.type || undefined,
      page: filters.page,
      per_page: 20,
    })
    customers.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

watch(
  () => [filters.search, filters.park_id, filters.status, filters.type],
  () => { filters.page = 1; load() },
)

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

const columns = [
  { key: 'name', label: 'Name', sortable: false },
  { key: 'type', label: 'Type', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'email', label: 'Email', sortable: false },
  { key: 'phone', label: 'Phone', sortable: false },
]

async function doExport() {
  const res = await exportCustomers({
    search: filters.search || undefined,
    park_id: filters.park_id || undefined,
    status: filters.status || undefined,
    type: filters.type || undefined,
  })
  const url = URL.createObjectURL(res.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'customers.xlsx'
  a.click()
  URL.revokeObjectURL(url)
}

// Create Modal
const showModal = ref(false)
const creating = ref(false)
const form = reactive({
  type: 'private' as 'private' | 'company',
  first_name: '',
  last_name: '',
  company_name: '',
  email: '',
  phone: '',
  birthdate: null as string | null,
  id_number: '',
  vat_id: '',
  street: '',
  house_number: '',
  zip: '',
  city: '',
  country: 'DE',
  gdpr_consent: false,
})

async function submitCreate() {
  if (!form.gdpr_consent) return
  creating.value = true
  try {
    await createCustomer({
      type: form.type,
      first_name: form.first_name,
      last_name: form.last_name,
      company_name: form.type === 'company' ? form.company_name : null,
      email: form.email,
      phone: form.phone,
      dob: form.birthdate || null,
      id_number: form.id_number || null,
      tax_id: form.type === 'company' ? form.vat_id : null,
      address: [form.street, form.house_number].filter(Boolean).join(' '),
      zip: form.zip,
      city: form.city,
      country: form.country,
      gdpr_consent_at: new Date().toISOString(),
    })
    showModal.value = false
    load()
  } finally {
    creating.value = false
  }
}

function customerName(c: Customer) {
  return c.type === 'company' && c.company_name
    ? c.company_name
    : c.first_name + ' ' + c.last_name
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Customers</h2>
      <div class="header-actions">
        <AppButton variant="secondary" size="sm" @click="doExport">Export</AppButton>
        <AppButton @click="showModal = true">+ New Customer</AppButton>
      </div>
    </div>

    <div class="filters">
      <input v-model="filters.search" class="search-input" placeholder="Search by name, email, ID..." />
      <select v-model="filters.park_id" class="filter-sel">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.status" class="filter-sel">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <select v-model="filters.type" class="filter-sel">
        <option v-for="opt in TYPE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <AppTable
      :columns="columns"
      :rows="(customers as unknown as Record<string, unknown>[])"
      @row-click="(row) => router.push('/customers/' + (row as unknown as Customer).id)"
    >
      <template #cell-name="{ row }">
        {{ customerName(row as unknown as Customer) }}
      </template>
      <template #cell-type="{ row }">
        <span class="type-badge">{{ (row as unknown as Customer).type }}</span>
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="(row as unknown as Customer).status" />
      </template>
      <template #empty>No customers found.</template>
    </AppTable>

    <AppPagination
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p) => { filters.page = p; load() }"
    />

    <!-- Create Modal -->
    <AppModal v-model="showModal" title="New Customer">
      <div class="modal-form">
        <!-- Type toggle -->
        <div class="type-toggle">
          <label :class="['type-opt', { active: form.type === 'private' }]">
            <input type="radio" v-model="form.type" value="private" />
            Private
          </label>
          <label :class="['type-opt', { active: form.type === 'company' }]">
            <input type="radio" v-model="form.type" value="company" />
            Company
          </label>
        </div>

        <FormInput v-if="form.type === 'company'" label="Company Name *" :model-value="form.company_name" @update:model-value="form.company_name = $event" required />
        <FormInput v-if="form.type === 'company'" label="VAT ID" :model-value="form.vat_id" @update:model-value="form.vat_id = $event" />

        <div class="row-2">
          <FormInput label="First Name *" :model-value="form.first_name" @update:model-value="form.first_name = $event" required />
          <FormInput label="Last Name *" :model-value="form.last_name" @update:model-value="form.last_name = $event" required />
        </div>

        <FormInput label="Email *" type="email" :model-value="form.email" @update:model-value="form.email = $event" required />
        <FormInput label="Phone *" :model-value="form.phone" @update:model-value="form.phone = $event" required />

        <div v-if="form.type === 'private'" class="row-2">
          <FormInput label="Birthdate" type="date" :model-value="form.birthdate ?? ''" @update:model-value="form.birthdate = $event" />
          <FormInput label="ID Number" :model-value="form.id_number" @update:model-value="form.id_number = $event" />
        </div>

        <div class="row-2">
          <FormInput label="Street *" :model-value="form.street" @update:model-value="form.street = $event" required />
          <FormInput label="House No *" :model-value="form.house_number" @update:model-value="form.house_number = $event" required />
        </div>
        <div class="row-2">
          <FormInput label="ZIP *" :model-value="form.zip" @update:model-value="form.zip = $event" required />
          <FormInput label="City *" :model-value="form.city" @update:model-value="form.city = $event" required />
        </div>
        <FormInput label="Country" :model-value="form.country" @update:model-value="form.country = $event" />

        <label class="gdpr-check">
          <input type="checkbox" v-model="form.gdpr_consent" />
          <span>I confirm the customer has given GDPR consent *</span>
        </label>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showModal = false">Cancel</AppButton>
        <AppButton :loading="creating" :disabled="!form.gdpr_consent" @click="submitCreate">Create</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 0.5rem; }

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.search-input { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; min-width: 200px; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }

.type-badge { background: #f1f5f9; border-radius: 4px; padding: 0.15rem 0.4rem; font-size: 0.8rem; color: #475569; text-transform: capitalize; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 420px; }
.type-toggle { display: flex; gap: 0.5rem; }
.type-opt { display: flex; align-items: center; gap: 0.375rem; padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; font-size: 0.875rem; }
.type-opt.active { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
.type-opt input { display: none; }
.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.gdpr-check { display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.875rem; color: #374151; cursor: pointer; }
</style>
