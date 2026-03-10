<script setup lang="ts">
defineProps<{
  label?: string
  modelValue: string | null
  error?: string
  required?: boolean
  disabled?: boolean
  min?: string
  max?: string
}>()

defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()
</script>

<template>
  <div class="form-field">
    <label v-if="label" class="field-label">
      {{ label }}<span v-if="required" class="required">*</span>
    </label>
    <input
      type="date"
      :value="modelValue ?? ''"
      :required="required"
      :disabled="disabled"
      :min="min"
      :max="max"
      :class="['form-input', { 'has-error': error }]"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <p v-if="error" class="field-error">{{ error }}</p>
  </div>
</template>

<style scoped>
.form-field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.required { color: #ef4444; margin-left: 2px; }

.form-input {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  outline: none;
  transition: border-color 0.15s;
}

.form-input:focus { border-color: #3b82f6; }
.form-input.has-error { border-color: #ef4444; }
.form-input:disabled { background: #f8fafc; opacity: 0.7; }
.field-error { font-size: 0.8rem; color: #ef4444; margin: 0; }
</style>
