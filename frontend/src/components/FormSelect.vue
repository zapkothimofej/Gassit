<script setup lang="ts">
export interface SelectOption {
  value: string | number
  label: string
}

defineProps<{
  label?: string
  modelValue: string | number | null
  options: SelectOption[]
  error?: string
  placeholder?: string
  required?: boolean
  disabled?: boolean
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
    <select
      :value="modelValue ?? ''"
      :required="required"
      :disabled="disabled"
      :class="['form-select', { 'has-error': error }]"
      @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
    </select>
    <p v-if="error" class="field-error">{{ error }}</p>
  </div>
</template>

<style scoped>
.form-field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.875rem; font-weight: 500; color: #374151; }
.required { color: #ef4444; margin-left: 2px; }

.form-select {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  background: #fff;
  outline: none;
  cursor: pointer;
  transition: border-color 0.15s;
}

.form-select:focus { border-color: #3b82f6; }
.form-select.has-error { border-color: #ef4444; }
.form-select:disabled { background: #f8fafc; opacity: 0.7; }

.field-error { font-size: 0.8rem; color: #ef4444; margin: 0; }
</style>
