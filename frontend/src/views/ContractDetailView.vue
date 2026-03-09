<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import StatusBadge from '../components/StatusBadge.vue'
import AppButton from '../components/AppButton.vue'
import AppModal from '../components/AppModal.vue'
import FormInput from '../components/FormInput.vue'
import FormSelect from '../components/FormSelect.vue'
import AppTable from '../components/AppTable.vue'
import DepositReturnModal from '../components/DepositReturnModal.vue'
import {
  fetchContract,
  sendForSignature,
  activateContract,
  terminateContract,
  renewContract,
  fetchContractDeposit,
  fetchInvoices,
  type ContractDetail,
  type Deposit,
  type Invoice,
} from '../api/contracts'

const route = useRoute()
const router = useRouter()
const contractId = Number(route.params.id)

const contract = ref<ContractDetail | null>(null)
const deposit = ref<Deposit | null>(null)
const invoices = ref<Invoice[]>([])
const loading = ref(true)
const activeTab = ref<'info' | 'invoices'>('info')

const STEPPER = ['draft', 'awaiting_signature', 'signed', 'active']

const stepperIndex = computed(() => {
  const s = contract.value?.status ?? 'draft'
  const idx = STEPPER.indexOf(s)
  return idx === -1 ? STEPPER.length : idx
})

const toast = ref('')
function showToast(msg: string) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 3000)
}

async function loadAll() {
  loading.value = true
  try {
    const res = await fetchContract(contractId)
    contract.value = res.data
    try {
      const dep = await fetchContractDeposit(contractId)
      deposit.value = dep.data
    } catch {
      deposit.value = null
    }
    const inv = await fetchInvoices({ contract_id: contractId, per_page: 50 })
    invoices.value = inv.data.data ?? []
  } finally {
    loading.value = false
  }
}

onMounted(loadAll)

// --- Send for signature ---
const sending = ref(false)
async function doSendSignature() {
  sending.value = true
  try {
    await sendForSignature(contractId)
    await loadAll()
    showToast('Contract sent for signature.')
  } finally {
    sending.value = false
  }
}

// --- Activate ---
const showActivateConfirm = ref(false)
const activating = ref(false)
async function doActivate() {
  activating.value = true
  try {
    await activateContract(contractId)
    await loadAll()
    showActivateConfirm.value = false
    showToast('Contract activated.')
  } finally {
    activating.value = false
  }
}

// --- Renew ---
const showRenewModal = ref(false)
const renewForm = ref({ start_date: '', rent_amount: '' })
const renewing = ref(false)
async function doRenew() {
  renewing.value = true
  try {
    const res = await renewContract(contractId, renewForm.value)
    showRenewModal.value = false
    showToast('Contract renewed.')
    const newId = (res.data as Record<string, Record<string, number>>).new_contract?.id
    if (newId) router.push('/contracts/' + newId)
  } finally {
    renewing.value = false
  }
}

// --- Terminate ---
const showTerminateModal = ref(false)
const termForm = ref({ termination_type: 'customer', termination_notice_date: '' })
const terminating = ref(false)
const TERMINATION_TYPES = [
  { value: 'customer', label: 'By Customer' },
  { value: 'lfg', label: 'By LFG' },
]
async function doTerminate() {
  terminating.value = true
  try {
    await terminateContract(contractId, termForm.value)
    await loadAll()
    showTerminateModal.value = false
    showToast('Contract terminated.')
  } finally {
    terminating.value = false
  }
}

// --- Return Deposit ---
const showReturnModal = ref(false)
async function onDepositReturned() {
  await loadAll()
  showToast('Deposit return initiated.')
}

function customerName(c: ContractDetail['customer']) {
  return c.type === 'company' && c.company_name ? c.company_name : c.first_name + ' ' + c.last_name
}

const invoiceColumns = [
  { key: 'invoice_number', label: 'Invoice #', sortable: false },
  { key: 'status', label: 'Status', sortable: false },
  { key: 'amount', label: 'Amount', sortable: false },
  { key: 'due_date', label: 'Due Date', sortable: false },
]
</script>

<template>
  <div class="page">
    <div class="back-link">
      <button class="btn-back" @click="router.back()">← Back to Contracts</button>
    </div>

    <div v-if="loading" class="loading">Loading...</div>

    <template v-else-if="contract">
      <!-- Header -->
      <div class="page-header">
        <div>
          <h2 class="contract-title">{{ contract.contract_number }}</h2>
          <StatusBadge :status="contract.status" />
        </div>
        <div class="header-actions">
          <AppButton
            v-if="contract.status === 'draft'"
            variant="secondary"
            :loading="sending"
            @click="doSendSignature"
          >Send for Signature</AppButton>
          <AppButton
            v-if="contract.status === 'signed'"
            @click="showActivateConfirm = true"
          >Activate</AppButton>
          <AppButton
            v-if="contract.status === 'active'"
            variant="secondary"
            @click="showRenewModal = true"
          >Renew</AppButton>
          <AppButton
            v-if="contract.status === 'active'"
            variant="danger"
            @click="showTerminateModal = true"
          >Terminate</AppButton>
        </div>
      </div>

      <!-- Stepper -->
      <div class="stepper">
        <div
          v-for="(step, i) in STEPPER"
          :key="step"
          :class="['step', { active: i === stepperIndex, done: i < stepperIndex }]"
        >
          <div class="step-dot">{{ i + 1 }}</div>
          <div class="step-label">{{ step.replace('_', ' ') }}</div>
        </div>
      </div>

      <!-- Toast -->
      <div v-if="toast" class="toast">{{ toast }}</div>

      <!-- Tabs -->
      <div class="tabs">
        <button :class="['tab-btn', { active: activeTab === 'info' }]" @click="activeTab = 'info'">Info</button>
        <button :class="['tab-btn', { active: activeTab === 'invoices' }]" @click="activeTab = 'invoices'">Invoices ({{ invoices.length }})</button>
      </div>

      <!-- Info Tab -->
      <div v-if="activeTab === 'info'" class="tab-content">
        <div class="cards-grid">
          <!-- Contract Info Card -->
          <div class="card">
            <h3 class="card-title">Contract Details</h3>
            <div class="info-grid">
              <div class="info-label">Customer</div>
              <div class="info-value">
                <a class="link" @click="router.push('/customers/' + contract.customer.id)">
                  {{ customerName(contract.customer) }}
                </a>
              </div>

              <div class="info-label">Unit</div>
              <div class="info-value">
                <a class="link" @click="router.push('/units/' + contract.unit.id)">
                  {{ contract.unit.unit_number }}
                </a>
              </div>

              <div class="info-label">Start Date</div>
              <div class="info-value">{{ contract.start_date }}</div>

              <div class="info-label">End Date</div>
              <div class="info-value">{{ contract.end_date ?? '–' }}</div>

              <div class="info-label">Rent</div>
              <div class="info-value">{{ contract.rent_amount }} €/month</div>

              <div class="info-label">Deposit</div>
              <div class="info-value">{{ contract.deposit_amount }} €</div>

              <div class="info-label">Insurance</div>
              <div class="info-value">{{ contract.insurance_amount }} €</div>

              <div class="info-label">Notice Period</div>
              <div class="info-value">{{ contract.notice_period_days }} days</div>

              <div v-if="contract.signed_at" class="info-label">Signed At</div>
              <div v-if="contract.signed_at" class="info-value">{{ contract.signed_at }}</div>

              <div v-if="contract.terminated_at" class="info-label">Terminated At</div>
              <div v-if="contract.terminated_at" class="info-value">{{ contract.terminated_at }}</div>
            </div>
          </div>

          <!-- Signatures Card -->
          <div class="card">
            <h3 class="card-title">Signatures</h3>
            <div v-if="contract.signatures.length === 0" class="empty-text">No signatures yet.</div>
            <div v-for="sig in contract.signatures" :key="sig.id" class="sig-row">
              <div class="sig-type">{{ sig.signer_type }}</div>
              <div class="sig-name">{{ sig.signer_name }}</div>
              <div class="sig-date">{{ sig.signed_at }}</div>
            </div>
          </div>

          <!-- Deposit Card -->
          <div class="card">
            <h3 class="card-title">Deposit</h3>
            <div v-if="!deposit" class="empty-text">No deposit record.</div>
            <template v-else>
              <div class="info-grid">
                <div class="info-label">Amount</div>
                <div class="info-value">{{ deposit.amount }} €</div>
                <div class="info-label">Status</div>
                <div class="info-value"><StatusBadge :status="deposit.status" /></div>
                <div v-if="deposit.received_at" class="info-label">Received At</div>
                <div v-if="deposit.received_at" class="info-value">{{ deposit.received_at }}</div>
                <div v-if="deposit.returned_at" class="info-label">Returned At</div>
                <div v-if="deposit.returned_at" class="info-value">{{ deposit.returned_at }}</div>
              </div>
              <AppButton
                v-if="['terminated_by_customer', 'terminated_by_lfg', 'expired'].includes(contract.status) && deposit.status !== 'returned'"
                class="mt-1"
                variant="secondary"
                size="sm"
                @click="showReturnModal = true"
              >Return Deposit</AppButton>
            </template>
          </div>
        </div>
      </div>

      <!-- Invoices Tab -->
      <div v-if="activeTab === 'invoices'" class="tab-content">
        <AppTable :columns="invoiceColumns" :rows="(invoices as unknown as Record<string, unknown>[])">
          <template #cell-status="{ row }">
            <StatusBadge :status="(row as unknown as Invoice).status" />
          </template>
          <template #cell-amount="{ row }">
            {{ (row as unknown as Invoice).amount }} €
          </template>
          <template #empty>No invoices for this contract.</template>
        </AppTable>
      </div>
    </template>

    <!-- Activate Confirmation -->
    <AppModal v-model="showActivateConfirm" title="Activate Contract">
      <p>Are you sure you want to activate this contract? This will mark the unit as rented and create a deposit record.</p>
      <template #footer>
        <AppButton variant="secondary" @click="showActivateConfirm = false">Cancel</AppButton>
        <AppButton :loading="activating" @click="doActivate">Activate</AppButton>
      </template>
    </AppModal>

    <!-- Renew Modal -->
    <AppModal v-model="showRenewModal" title="Renew Contract">
      <div class="modal-form">
        <FormInput label="New Start Date *" type="date" :model-value="renewForm.start_date" @update:model-value="renewForm.start_date = $event" required />
        <FormInput label="New Rent Amount (€) *" type="number" :model-value="renewForm.rent_amount" @update:model-value="renewForm.rent_amount = $event" required />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showRenewModal = false">Cancel</AppButton>
        <AppButton :loading="renewing" @click="doRenew">Renew</AppButton>
      </template>
    </AppModal>

    <!-- Terminate Modal -->
    <AppModal v-model="showTerminateModal" title="Terminate Contract">
      <div class="modal-form">
        <FormSelect
          label="Termination Type *"
          :model-value="termForm.termination_type"
          @update:model-value="termForm.termination_type = $event"
          :options="TERMINATION_TYPES"
        />
        <FormInput
          label="Notice Date *"
          type="date"
          :model-value="termForm.termination_notice_date"
          @update:model-value="termForm.termination_notice_date = $event"
          required
        />
      </div>
      <template #footer>
        <AppButton variant="secondary" @click="showTerminateModal = false">Cancel</AppButton>
        <AppButton variant="danger" :loading="terminating" @click="doTerminate">Terminate</AppButton>
      </template>
    </AppModal>

    <!-- Return Deposit Modal -->
    <DepositReturnModal
      v-if="deposit"
      v-model="showReturnModal"
      :deposit="deposit"
      :contract-id="contractId"
      @done="onDepositReturned"
    />
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; gap: 1rem; }
.back-link { margin-bottom: 0.25rem; }
.btn-back { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 0.875rem; padding: 0; }
.btn-back:hover { text-decoration: underline; }

.page-header { display: flex; justify-content: space-between; align-items: flex-start; }
.contract-title { margin: 0 0 0.25rem 0; font-size: 1.5rem; }
.header-actions { display: flex; gap: 0.5rem; }

.stepper { display: flex; gap: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.step { flex: 1; display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; border-right: 1px solid #e2e8f0; color: #94a3b8; }
.step:last-child { border-right: none; }
.step.done { color: #22c55e; }
.step.active { color: #3b82f6; background: #eff6ff; }
.step-dot { width: 24px; height: 24px; border-radius: 50%; border: 2px solid currentColor; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 600; flex-shrink: 0; }
.step.done .step-dot { background: #22c55e; border-color: #22c55e; color: #fff; }
.step.active .step-dot { background: #3b82f6; border-color: #3b82f6; color: #fff; }
.step-label { font-size: 0.8rem; font-weight: 500; text-transform: capitalize; }

.toast { background: #22c55e; color: #fff; padding: 0.75rem 1.25rem; border-radius: 6px; text-align: center; }

.tabs { display: flex; border-bottom: 2px solid #e2e8f0; }
.tab-btn { border: none; background: none; padding: 0.625rem 1.25rem; cursor: pointer; font-size: 0.875rem; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s; }
.tab-btn.active { color: #3b82f6; border-bottom-color: #3b82f6; font-weight: 500; }

.tab-content { padding-top: 0.5rem; }
.cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; }
.card-title { margin: 0 0 1rem 0; font-size: 1rem; font-weight: 600; }

.info-grid { display: grid; grid-template-columns: 140px 1fr; gap: 0.5rem 1rem; font-size: 0.875rem; }
.info-label { color: #64748b; }
.info-value { color: #1e293b; font-weight: 500; }
.link { color: #3b82f6; cursor: pointer; }
.link:hover { text-decoration: underline; }

.empty-text { color: #94a3b8; font-size: 0.875rem; }

.sig-row { display: grid; grid-template-columns: 80px 1fr auto; gap: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
.sig-type { color: #64748b; text-transform: capitalize; }
.sig-name { font-weight: 500; }
.sig-date { color: #94a3b8; }

.mt-1 { margin-top: 0.75rem; }
.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 360px; }
.loading { color: #64748b; padding: 2rem; text-align: center; }
</style>
