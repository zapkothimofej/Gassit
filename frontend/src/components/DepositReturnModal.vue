<script setup lang="ts">
import { ref, computed } from 'vue'
import AppModal from './AppModal.vue'
import AppButton from './AppButton.vue'
import FormInput from './FormInput.vue'
import FormSelect from './FormSelect.vue'
import FormTextarea from './FormTextarea.vue'
import { returnDeposit, type Deposit } from '../api/contracts'

const props = defineProps<{
  modelValue: boolean
  deposit: Deposit
  contractId: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'done'): void
}>()

const deductionAmount = ref('')
const deductionReason = ref<string | null>('')
const returnMethod = ref('bank_transfer')
const iban = ref('')
const submitting = ref(false)
const errorMsg = ref('')

const RETURN_METHODS = [
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'mollie_payout', label: 'Mollie Payout' },
]

const depositAmt = computed(() => parseFloat(props.deposit.amount) || 0)
const deductAmt = computed(() => parseFloat(deductionAmount.value) || 0)
const returnAmt = computed(() => Math.max(0, depositAmt.value - deductAmt.value))

async function submit() {
  submitting.value = true
  errorMsg.value = ''
  try {
    await returnDeposit(props.deposit.id, {
      deduction_amount: deductAmt.value > 0 ? deductAmt.value : null,
      deduction_reason: deductionReason.value || null,
      return_method: returnMethod.value,
    })
    emit('update:modelValue', false)
    emit('done')
  } catch (err: unknown) {
    const e = err as { response?: { data?: { message?: string } } }
    errorMsg.value = e.response?.data?.message ?? 'An error occurred.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppModal :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)" title="Return Deposit">
    <div class="modal-form">
      <div class="summary-row">
        <span class="summary-label">Deposit Amount</span>
        <span class="summary-value">{{ depositAmt.toFixed(2) }} €</span>
      </div>

      <FormInput
        label="Deduction Amount (€)"
        type="number"
        :model-value="deductionAmount"
        @update:model-value="deductionAmount = $event"
        placeholder="0.00"
      />

      <FormTextarea
        v-if="deductAmt > 0"
        label="Deduction Reason"
        :model-value="deductionReason"
        @update:model-value="deductionReason = $event"
        placeholder="Describe the reason for deduction..."
        :rows="3"
      />

      <div class="summary-row return-row">
        <span class="summary-label">Return Amount</span>
        <span class="return-amount">{{ returnAmt.toFixed(2) }} €</span>
      </div>

      <FormSelect
        label="Return Method *"
        :model-value="returnMethod"
        @update:model-value="returnMethod = $event"
        :options="RETURN_METHODS"
      />

      <FormInput
        v-if="returnMethod === 'bank_transfer'"
        label="IBAN"
        :model-value="iban"
        @update:model-value="iban = $event"
        placeholder="DE89 3704 0044 0532 0130 00"
      />

      <p v-if="returnMethod === 'mollie_payout'" class="mollie-note">
        A Mollie payout will be initiated after confirmation.
      </p>

      <p v-if="errorMsg" class="error-msg">{{ errorMsg }}</p>
    </div>

    <template #footer>
      <AppButton variant="secondary" @click="$emit('update:modelValue', false)">Cancel</AppButton>
      <AppButton :loading="submitting" @click="submit">Confirm Return</AppButton>
    </template>
  </AppModal>
</template>

<style scoped>
.modal-form { display: flex; flex-direction: column; gap: 0.875rem; min-width: 380px; }

.summary-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0.75rem; background: #f8fafc; border-radius: 6px; }
.summary-label { font-size: 0.875rem; color: #64748b; }
.summary-value { font-size: 0.875rem; font-weight: 600; color: #1e293b; }

.return-row { background: #f0fdf4; }
.return-amount { font-size: 1.125rem; font-weight: 700; color: #15803d; }

.mollie-note { font-size: 0.8rem; color: #64748b; background: #eff6ff; padding: 0.5rem 0.75rem; border-radius: 6px; margin: 0; }
.error-msg { font-size: 0.8rem; color: #ef4444; margin: 0; }
</style>
