<script setup lang="ts">
import { ref, reactive, watch, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppTable from '../components/AppTable.vue'
import AppPagination from '../components/AppPagination.vue'
import StatusBadge from '../components/StatusBadge.vue'
import AppModal from '../components/AppModal.vue'
import AppButton from '../components/AppButton.vue'
import FormInput from '../components/FormInput.vue'
import {
  fetchInvoices,
  createInvoice,
  datevExport,
  searchCustomers,
  type InvoiceSummary,
  type CreateInvoiceItem,
} from '../api/invoices'
import { fetchParks } from '../api/parks'

const auth = useAuthStore()
const router = useRouter()

const filters = reactive({
  park_id: null as number | null,
  status: '',
  from: '',
  to: '',
  page: 1,
})

const invoices = ref<InvoiceSummary[]>([])
const totalPages = ref(1)
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'draft', label: 'Draft' },
  { value: 'sent', label: 'Sent' },
  { value: 'paid', label: 'Paid' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'cancelled', label: 'Cancelled' },
]

async function load() {
  loading.value = true
  try {
    const res = await fetchInvoices({
      park_id: filters.park_id || undefined,
      status: filters.status || undefined,
      from: filters.from || undefined,
      to: filters.to || undefined,
      page: filters.page,
      per_page: 20,
    })
    invoices.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

watch(
  () => [filters.park_id, filters.status, filters.from, filters.to],
  () => { filters.page = 1; load() },
)

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

const columns = [
  { key: 'invoice_number', label: 'Invoice #', sortable: false },
  { key: 'customer', label: 'Customer', sortable: false },
  { key: 'park', label: 'Park', sortable: false },
  { key: 'total_amount', label: 'Total', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'due_date', label: 'Due Date', sortable: false },
]

function customerName(inv: InvoiceSummary) {
  const c = inv.customer
  return c.type === 'company' && c.company_name ? c.company_name : c.first_name + ' ' + c.last_name
}

const isAccountant = computed(() =>
  ['admin', 'main_manager', 'accountant'].includes(auth.role ?? ''),
)

// DATEV Export
const showDatevModal = ref(false)
const datevForm = reactive({ from: '', to: '' })
const datevLoading = ref(false)
async function doDatevExport() {
  datevLoading.value = true
  try {
    const res = await datevExport({ from: datevForm.from, to: datevForm.to, park_id: filters.park_id || undefined })
    const url = URL.createObjectURL(res.data as Blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'datev-export.csv'
    a.click()
    URL.revokeObjectURL(url)
    showDatevModal.value = false
  } finally {
    datevLoading.value = false
  }
}

// Create Invoice Modal
const showCreateModal = ref(false)
const creating = ref(false)
const customerSearch = ref('')
const customerResults = ref<Array<{ id: number; first_name: string; last_name: string; company_name: string | null; type: string }>>([])
const cForm = reactive({
  customer_id: null as number | null,
  customer_label: '',
  park_id: null as number | null,
  due_date: '',
  tax_rate: 0,
  items: [{ description: '', quantity: 1, unit_price: 0 }] as CreateInvoiceItem[],
})

let searchTimer: ReturnType<typeof setTimeout>
async function onCustomerSearch() {
  clearTimeout(searchTimer)
  if (!customerSearch.value.trim()) { customerResults.value = []; return }
  searchTimer = setTimeout(async () => {
    const res = await searchCustomers(customerSearch.value)
    customerResults.value = res.data.data ?? []
  }, 300)
}

function selectCustomer(c: { id: number; first_name: string; last_name: string; company_name: string | null; type: string }) {
  cForm.customer_id = c.id
  cForm.customer_label = c.type === 'company' && c.company_name ? c.company_name : c.first_name + ' ' + c.last_name
  customerResults.value = []
  customerSearch.value = cForm.customer_label
}

function addItem() {
  cForm.items.push({ description: '', quantity: 1, unit_price: 0 })
}

function removeItem(i: number) {
  if (cForm.items.length > 1) cForm.items.splice(i, 1)
}

async function submitCreate() {
  if (!cForm.customer_id || !cForm.park_id || !cForm.due_date) return
  creating.value = true
  try {
    const res = await createInvoice({
      customer_id: cForm.customer_id,
      park_id: cForm.park_id,
      due_date: cForm.due_date,
      tax_rate: cForm.tax_rate || 0,
      items: cForm.items,
    })
    showCreateModal.value = false
    router.push('/invoices/' + res.data.id)
  } finally {
    creating.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Invoices</h2>
      <div class="header-actions">
        <AppButton v-if="isAccountant" variant="secondary" size="sm" @click="showDatevModal = true">DATEV Export</AppButton>
        <AppButton @click="showCreateModal = true">+ New Invoice</AppButton>
      </div>
    </div>

    <div class="filters">
      <select v-model="filters.park_id" class="filter-sel">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.status" class="filter-sel">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <div class="date-range">
        <label class="date-label">From:</label>
        <input v-model="filters.from" type="date" class="filter-sel" />
        <span class="date-sep">–</span>
        <input v-model="filters.to" type="date" class="filter-sel" />
      </div>
    </div>

    <AppTable
      :columns="columns"
      :rows="(invoices as unknown as Record<string, unknown>[])"
      @row-click="(row) => router.push('/invoices/' + (row as unknown as InvoiceSummary).id)"
    >
      <template #cell-invoice_number="{ row }">
        <span class="inv-num">{{ (row as unknown as InvoiceSummary).invoice_number }}</span>
      </template>
      <template #cell-customer="{ row }">
        {{ customerName(row as unknown as InvoiceSummary) }}
      </template>
      <template #cell-park="{ row }">
        {{ (row as unknown as InvoiceSummary).park?.name ?? '–' }}
      </template>
      <template #cell-total_amount="{ row }">
        {{ (row as unknown as InvoiceSummary).total_amount }} €
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="(row as unknown as InvoiceSummary).status" />
      </template>
      <template #empty>No invoices found.</template>
    </AppTable>

    <AppPagination
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p) => { filters.page = p; load() }"
    />

    <!-- DATEV Export Modal -->
    <AppModal v-model="showDatevModal" title="DATEV Export">
      <div class="modal-form">
        <FormInput label="From *" type="date" :model-value="datevForm.from" @update:model-value="datevForm.from = $event" required />
        <FormInput label="To *" type="date" :model-value="datevForm.to" @update:model-value="datevForm.to = $event" required />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showDatevModal = false">Cancel</AppButton>
        <AppButton :loading="datevLoading" @click="doDatevExport">Download CSV</AppButton>
      </template>
    </AppModal>

    <!-- Create Invoice Modal -->
    <AppModal v-model="showCreateModal" title="New Invoice">
      <div class="modal-form wide">
        <!-- Customer search -->
        <div class="field-group">
          <label class="field-label">Customer *</label>
          <div class="autocomplete">
            <input
              v-model="customerSearch"
              class="search-input"
              placeholder="Search by name..."
              @input="onCustomerSearch"
            />
            <div v-if="customerResults.length > 0" class="dropdown">
              <div
                v-for="c in customerResults"
                :key="c.id"
                class="dropdown-item"
                @click="selectCustomer(c)"
              >
                {{ c.type === 'company' && c.company_name ? c.company_name : c.first_name + ' ' + c.last_name }}
              </div>
            </div>
          </div>
        </div>

        <div class="row-2">
          <div>
            <label class="field-label">Park *</label>
            <select v-model="cForm.park_id" class="filter-sel full-w">
              <option :value="null">Select park…</option>
              <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <FormInput label="Due Date *" type="date" :model-value="cForm.due_date" @update:model-value="cForm.due_date = $event" required />
        </div>

        <FormInput label="Tax Rate (%)" type="number" :model-value="String(cForm.tax_rate)" @update:model-value="cForm.tax_rate = Number($event)" />

        <!-- Line items -->
        <div class="items-section">
          <div class="items-header">
            <span class="field-label">Line Items</span>
            <button class="btn-add-item" @click="addItem">+ Add Row</button>
          </div>
          <div v-for="(item, i) in cForm.items" :key="i" class="item-row">
            <input v-model="item.description" class="item-desc" placeholder="Description" />
            <input v-model.number="item.quantity" class="item-num" type="number" min="0.01" step="0.01" placeholder="Qty" />
            <input v-model.number="item.unit_price" class="item-num" type="number" min="0" step="0.01" placeholder="Price" />
            <span class="item-total">{{ (item.quantity * item.unit_price).toFixed(2) }} €</span>
            <button v-if="cForm.items.length > 1" class="btn-remove" @click="removeItem(i)">×</button>
          </div>
        </div>
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showCreateModal = false">Cancel</AppButton>
        <AppButton :loading="creating" :disabled="!cForm.customer_id || !cForm.park_id" @click="submitCreate">Create</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 0.5rem; }

.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.date-range { display: flex; align-items: center; gap: 0.375rem; }
.date-label { font-size: 0.8rem; color: #64748b; white-space: nowrap; }
.date-sep { color: #94a3b8; }

.inv-num { font-family: monospace; font-size: 0.85rem; color: #1d4ed8; }

.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 420px; }
.modal-form.wide { min-width: 560px; }

.field-group { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; }

.autocomplete { position: relative; }
.search-input { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; box-sizing: border-box; }
.dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,.08); z-index: 100; max-height: 200px; overflow-y: auto; }
.dropdown-item { padding: 0.5rem 0.75rem; font-size: 0.875rem; cursor: pointer; }
.dropdown-item:hover { background: #f1f5f9; }

.row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.full-w { width: 100%; box-sizing: border-box; }

.items-section { display: flex; flex-direction: column; gap: 0.5rem; }
.items-header { display: flex; justify-content: space-between; align-items: center; }
.btn-add-item { border: none; background: none; color: #3b82f6; cursor: pointer; font-size: 0.875rem; padding: 0; }
.btn-add-item:hover { text-decoration: underline; }

.item-row { display: grid; grid-template-columns: 1fr 80px 80px 80px 28px; gap: 0.375rem; align-items: center; }
.item-desc { border: 1px solid #cbd5e1; border-radius: 4px; padding: 0.3rem 0.5rem; font-size: 0.8rem; }
.item-num { border: 1px solid #cbd5e1; border-radius: 4px; padding: 0.3rem 0.5rem; font-size: 0.8rem; text-align: right; }
.item-total { font-size: 0.8rem; color: #374151; font-weight: 500; text-align: right; }
.btn-remove { border: none; background: none; color: #ef4444; cursor: pointer; font-size: 1rem; padding: 0; line-height: 1; }
</style>
