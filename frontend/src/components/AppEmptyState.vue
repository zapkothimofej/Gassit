<script setup lang="ts">
defineProps<{
  icon?: string
  message: string
}>()
</script>

<template>
  <div class="empty-state">
    <div class="empty-icon">{{ icon ?? '📭' }}</div>
    <p class="empty-message">{{ message }}</p>
    <div v-if="$slots.action" class="empty-action">
      <slot name="action" />
    </div>
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  gap: 0.75rem;
  text-align: center;
}

.empty-icon {
  font-size: 2.5rem;
  opacity: 0.5;
}

.empty-message {
  font-size: 0.9375rem;
  color: #64748b;
  margin: 0;
  max-width: 320px;
}

.empty-action {
  margin-top: 0.5rem;
}
</style>
