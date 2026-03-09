<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StatusBadge from '../components/StatusBadge.vue'
import AppButton from '../components/AppButton.vue'
import AppModal from '../components/AppModal.vue'
import AppTable from '../components/AppTable.vue'
import {
  fetchInvoice,
  sendInvoice,
  cancelInvoice,
  createPaymentLink,
  getInvoicePdfUrl,
  type InvoiceDetail,
} from '../api/invoices'

const route = useRoute()
const router = useRouter()
const invoiceId = Number(route.params.id)

const invoice = ref<InvoiceDetail | null>(null)
const loading = ref(true)

const toast = ref('')
function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

async function load() {
  loading.value = true
  try {
    const res = await fetchInvoice(invoiceId)
    invoice.value = res.data
  } finally {
    loading.value = false
  }
}
onMounted(load)

// --- PDF ---
function openPdf() {
  window.open(getInvoicePdfUrl(invoiceId), '_blank')
}

// --- Send ---
const sending = ref(false)
async function doSend() {
  sending.value = true
  try {
    await sendInvoice(invoiceId)
    await load()
    showToast('Invoice sent by email.')
  } finally {
    sending.value = false
  }
}

// --- Cancel ---
const showCancelConfirm = ref(false)
const cancelling = ref(false)
async function doCancel() {
  cancelling.value = true
  try {
    await cancelInvoice(invoiceId)
    await load()
    showCancelConfirm.value = false
    showToast('Invoice cancelled.')
  } finally {
    cancelling.value = false
  }
}

// --- Payment Link ---
const showPaymentModal = ref(false)
const paymentLink = ref('')
const generatingLink = ref(false)
const linkCopied = ref(false)

async function generateLink() {
  generatingLink.value = true
  try {
    const res = await createPaymentLink(invoiceId)
    paymentLink.value = res.data.payment_url
    showPaymentModal.value = true
  } finally {
    generatingLink.value = false
  }
}

async function copyLink() {
  await navigator.clipboard.writeText(paymentLink.value)
  linkCopied.value = true
  setTimeout(() => { linkCopied.value = false }, 2000)
}

function customerName(inv: InvoiceDetail) {
  const c = inv.customer
  return c.type === 'company' && c.company_name ? c.company_name : c.first_name + ' ' + c.last_name
}

const itemColumns = [
  { key: 'description', label: 'Description', sortable: false },
  { key: 'quantity', label: 'Qty', sortable: false },
  { key: 'unit_price', label: 'Unit Price', sortable: false },
  { key: 'total', label: 'Total', sortable: false },
]
</script>

<template>
  <div class="page">
    <div class="back-link">
      <button class="btn-back" @click="router.back()">← Back to Invoices</button>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <template v-else-if="invoice">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h2 class="inv-title">{{ invoice.invoice_number }}</h2>
          <StatusBadge :status="invoice.status" />
        </div>
        <div class="header-actions">
          <AppButton variant="secondary" size="sm" @click="openPdf">Download PDF</AppButton>
          <AppButton
            v-if="['draft', 'sent'].includes(invoice.status)"
            variant="secondary"
            size="sm"
            :loading="sending"
            @click="doSend"
          >Send Email</AppButton>
          <AppButton
            v-if="['sent', 'overdue'].includes(invoice.status)"
            variant="secondary"
            size="sm"
            :loading="generatingLink"
            @click="generateLink"
          >Payment Link</AppButton>
          <AppButton
            v-if="!['cancelled', 'paid'].includes(invoice.status)"
            variant="danger"
            size="sm"
            @click="showCancelConfirm = true"
          >Cancel</AppButton>
        </div>
      </div>

      <div v-if="toast" class="toast">{{ toast }}</div>

      <!-- Info cards -->
      <div class="cards-row">
        <div class="card">
          <h3 class="card-title">Invoice Details</h3>
          <div class="info-grid">
            <div class="info-label">Customer</div>
            <div class="info-value">
              <a class="link" @click="router.push('/customers/' + invoice.customer.id)">
                {{ customerName(invoice) }}
              </a>
            </div>
            <div class="info-label">Park</div>
            <div class="info-value">{{ invoice.park?.name ?? '–' }}</div>
            <div class="info-label">Issue Date</div>
            <div class="info-value">{{ invoice.issue_date }}</div>
            <div class="info-label">Due Date</div>
            <div class="info-value">{{ invoice.due_date }}</div>
            <div v-if="invoice.contract_id" class="info-label">Contract</div>
            <div v-if="invoice.contract_id" class="info-value">
              <a class="link" @click="router.push('/contracts/' + invoice.contract_id)">#{{ invoice.contract_id }}</a>
            </div>
          </div>
        </div>

        <div class="card">
          <h3 class="card-title">Totals</h3>
          <div class="info-grid">
            <div class="info-label">Subtotal</div>
            <div class="info-value">{{ invoice.subtotal }} €</div>
            <div class="info-label">Tax ({{ invoice.tax_rate }}%)</div>
            <div class="info-value">{{ invoice.tax_amount }} €</div>
            <div class="info-label total-label">Total</div>
            <div class="info-value total-value">{{ invoice.total_amount }} €</div>
          </div>
        </div>
      </div>

      <!-- Line Items -->
      <div class="section">
        <h3 class="section-title">Line Items</h3>
        <AppTable :columns="itemColumns" :rows="(invoice.items as unknown as Record<string, unknown>[])">
          <template #cell-quantity="{ row }">
            {{ (row as unknown as { quantity: string }).quantity }}
          </template>
          <template #cell-unit_price="{ row }">
            {{ (row as unknown as { unit_price: string }).unit_price }} €
          </template>
          <template #cell-total="{ row }">
            {{ (row as unknown as { total: string }).total }} €
          </template>
          <template #empty>No items.</template>
        </AppTable>
      </div>
    </template>

    <!-- Cancel Confirmation -->
    <AppModal v-model="showCancelConfirm" title="Cancel Invoice">
      <p>Are you sure you want to cancel this invoice? A credit note will be generated.</p>
      <template #footer>
        <AppButton variant="secondary" @click="showCancelConfirm = false">Back</AppButton>
        <AppButton variant="danger" :loading="cancelling" @click="doCancel">Cancel Invoice</AppButton>
      </template>
    </AppModal>

    <!-- Payment Link Modal -->
    <AppModal v-model="showPaymentModal" title="Payment Link">
      <div class="payment-modal">
        <p class="payment-desc">Share this link with the customer to collect payment via Mollie:</p>
        <div class="link-row">
          <span class="payment-link">{{ paymentLink }}</span>
          <AppButton size="sm" variant="secondary" @click="copyLink">
            {{ linkCopied ? 'Copied!' : 'Copy' }}
          </AppButton>
        </div>
      </div>
      <template #footer>
        <AppButton @click="showPaymentModal = false">Close</AppButton>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.back-link { margin-bottom: 0.25rem; }
.btn-back { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 0.875rem; padding: 0; }
.btn-back:hover { text-decoration: underline; }

.page-header { display: flex; justify-content: space-between; align-items: flex-start; }
.inv-title { margin: 0 0 0.25rem 0; font-size: 1.5rem; font-family: monospace; }
.header-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

.toast { background: #22c55e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 6px; text-align: center; }

.cards-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; }
.card-title { margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; }

.info-grid { display: grid; grid-template-columns: 130px 1fr; gap: 0.5rem 1rem; font-size: 0.875rem; }
.info-label { color: #64748b; }
.info-value { color: #1e293b; font-weight: 500; }
.total-label { font-weight: 700; color: #1e293b; }
.total-value { font-weight: 700; font-size: 1.1rem; color: #1e293b; }
.link { color: #3b82f6; cursor: pointer; }
.link:hover { text-decoration: underline; }

.section { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; }
.section-title { margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; }

.payment-modal { display: flex; flex-direction: column; gap: 0.75rem; min-width: 380px; }
.payment-desc { font-size: 0.875rem; color: #64748b; margin: 0; }
.link-row { display: flex; gap: 0.75rem; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.5rem 0.75rem; }
.payment-link { font-family: monospace; font-size: 0.8rem; color: #1d4ed8; flex: 1; word-break: break-all; }
.loading { color: #64748b; padding: 2rem; text-align: center; }
</style>
