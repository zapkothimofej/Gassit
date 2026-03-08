<script setup lang="ts">
defineProps<{
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  disabled?: boolean
  type?: 'button' | 'submit' | 'reset'
}>()
</script>

<template>
  <button
    :type="type ?? 'button'"
    :disabled="disabled || loading"
    :class="['app-btn', `btn-${variant ?? 'primary'}`, `btn-${size ?? 'md'}`, { loading }]"
  >
    <span v-if="loading" class="spinner" />
    <slot />
  </button>
</template>

<style scoped>
.app-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: opacity 0.15s, background 0.15s;
}

.app-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* Variants */
.btn-primary   { background: #3b82f6; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #2563eb; }
.btn-secondary { background: #f1f5f9; color: #374151; border: 1px solid #e2e8f0; }
.btn-secondary:hover:not(:disabled) { background: #e2e8f0; }
.btn-danger    { background: #ef4444; color: #fff; }
.btn-danger:hover:not(:disabled) { background: #dc2626; }
.btn-ghost     { background: transparent; color: #374151; }
.btn-ghost:hover:not(:disabled) { background: #f1f5f9; }

/* Sizes */
.btn-sm { padding: 0.25rem 0.625rem; font-size: 0.8rem; }
.btn-md { padding: 0.5rem 1rem;      font-size: 0.875rem; }
.btn-lg { padding: 0.75rem 1.5rem;   font-size: 1rem; }

/* Spinner */
.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: currentColor;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }
</style>
