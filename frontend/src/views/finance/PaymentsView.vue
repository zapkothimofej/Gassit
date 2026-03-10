<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppTable from '../../components/AppTable.vue'
import AppPagination from '../../components/AppPagination.vue'
import StatusBadge from '../../components/StatusBadge.vue'
import AppModal from '../../components/AppModal.vue'
import AppButton from '../../components/AppButton.vue'
import api from '../../api/axios'
import { fetchParks } from '../../api/parks'

const router = useRouter()

interface Payment {
  id: number
  amount: string
  status: string
  payment_method: string
  paid_at: string | null
  mollie_payment_id: string | null
  invoice: {
    id: number
    invoice_number: string
    customer: { id: number; first_name: string; last_name: string; company_name: string | null; type: string }
    park: { id: number; name: string }
  }
}

const payments = ref<Payment[]>([])
const totalPages = ref(1)
const loading = ref(false)
const parks = ref<Array<{ id: number; name: string }>>([])

const filters = reactive({
  park_id: null as number | null,
  status: '',
  method: '',
  from: '',
  to: '',
  page: 1,
})

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'pending', label: 'Pending' },
  { value: 'paid', label: 'Paid' },
  { value: 'refunded', label: 'Refunded' },
  { value: 'failed', label: 'Failed' },
]

const METHOD_OPTIONS = [
  { value: '', label: 'All Methods' },
  { value: 'mollie', label: 'Mollie' },
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'cash', label: 'Cash' },
]

async function load() {
  loading.value = true
  try {
    const res = await api.get<{ data: Payment[]; last_page: number }>('/payments', {
      params: {
        park_id: filters.park_id || undefined,
        status: filters.status || undefined,
        method: filters.method || undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
        page: filters.page,
        per_page: 20,
      },
    })
    payments.value = res.data.data ?? []
    totalPages.value = res.data.last_page ?? 1
  } finally {
    loading.value = false
  }
}

watch(
  () => [filters.park_id, filters.status, filters.method, filters.from, filters.to],
  () => { filters.page = 1; load() },
)

onMounted(async () => {
  load()
  const pr = await fetchParks()
  parks.value = pr.data.data ?? []
})

const columns = [
  { key: 'id', label: 'Payment ID', sortable: false },
  { key: 'invoice_number', label: 'Invoice #', sortable: false },
  { key: 'customer', label: 'Customer', sortable: false },
  { key: 'amount', label: 'Amount', sortable: false },
  { key: 'payment_method', label: 'Method', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'paid_at', label: 'Paid At', sortable: false },
  { key: 'actions', label: '', sortable: false },
]

function customerName(p: Payment) {
  const c = p.invoice.customer
  return c.type === 'company' && c.company_name ? c.company_name : c.first_name + ' ' + c.last_name
}

// Refund
const showRefundModal = ref(false)
const refundTarget = ref<Payment | null>(null)
const refunding = ref(false)
function openRefund(p: Payment) { refundTarget.value = p; showRefundModal.value = true }
async function doRefund() {
  if (!refundTarget.value) return
  refunding.value = true
  try {
    await api.post('/payments/' + refundTarget.value.id + '/refund')
    showRefundModal.value = false
    await load()
  } finally {
    refunding.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="page-header">
      <h2>Payments</h2>
    </div>

    <div class="filters">
      <select v-model="filters.park_id" class="filter-sel">
        <option :value="null">All Parks</option>
        <option v-for="p in parks" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <select v-model="filters.status" class="filter-sel">
        <option v-for="opt in STATUS_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <select v-model="filters.method" class="filter-sel">
        <option v-for="opt in METHOD_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
      <div class="date-range">
        <label class="date-label">From:</label>
        <input v-model="filters.from" type="date" class="filter-sel" />
        <span>–</span>
        <input v-model="filters.to" type="date" class="filter-sel" />
      </div>
    </div>

    <AppTable :columns="columns" :rows="(payments as unknown as Record<string, unknown>[])">
      <template #cell-id="{ row }">
        <span class="mono">#{{ (row as unknown as Payment).id }}</span>
      </template>
      <template #cell-invoice_number="{ row }">
        <a class="link" @click.stop="router.push('/invoices/' + (row as unknown as Payment).invoice.id)">
          {{ (row as unknown as Payment).invoice.invoice_number }}
        </a>
      </template>
      <template #cell-customer="{ row }">
        {{ customerName(row as unknown as Payment) }}
      </template>
      <template #cell-amount="{ row }">
        <span class="amount">{{ (row as unknown as Payment).amount }} €</span>
      </template>
      <template #cell-payment_method="{ row }">
        <span class="method-badge">{{ (row as unknown as Payment).payment_method }}</span>
      </template>
      <template #cell-status="{ row }">
        <StatusBadge :status="(row as unknown as Payment).status" />
      </template>
      <template #cell-paid_at="{ row }">
        {{ (row as unknown as Payment).paid_at ?? '–' }}
      </template>
      <template #cell-actions="{ row }">
        <button
          v-if="(row as unknown as Payment).status === 'paid'"
          class="btn-refund"
          @click.stop="openRefund(row as unknown as Payment)"
        >Refund</button>
      </template>
      <template #empty>No payments found.</template>
    </AppTable>

    <AppPagination
      :current-page="filters.page"
      :total-pages="totalPages"
      @page-change="(p) => { filters.page = p; load() }"
    />

    <AppModal v-model="showRefundModal" title="Refund Payment">
      <p>Refund payment of <strong>{{ refundTarget?.amount }} €</strong>? This will initiate a refund via the payment provider.</p>
      <template #footer>
        <AppButton variant="secondary" @click="showRefundModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="refunding" @click="doRefund">Confirm Refund</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-header h2 { margin: 0; }
.filters { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.75rem 1rem; }
.filter-sel { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; }
.date-range { display: flex; align-items: center; gap: 0.375rem; }
.date-label { font-size: 0.8rem; color: #64748b; }
.mono { font-family: monospace; font-size: 0.85rem; }
.link { color: #3b82f6; cursor: pointer; }
.link:hover { text-decoration: underline; }
.amount { font-weight: 600; color: #1e293b; }
.method-badge { background: #f1f5f9; border-radius: 4px; padding: 0.15rem 0.5rem; font-size: 0.8rem; color: #475569; text-transform: capitalize; }
.btn-refund { border: 1px solid #ef4444; color: #ef4444; background: none; border-radius: 4px; padding: 0.2rem 0.6rem; font-size: 0.8rem; cursor: pointer; }
.btn-refund:hover { background: #fef2f2; }
</style>
