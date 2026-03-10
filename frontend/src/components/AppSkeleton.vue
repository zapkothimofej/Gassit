<script setup lang="ts">
withDefaults(defineProps<{ rows?: number; cols?: number }>(), { rows: 5, cols: 4 })
</script>

<template>
  <div class="skeleton-wrap">
    <!-- Header row -->
    <div class="skeleton-header">
      <div v-for="i in cols" :key="i" class="skeleton-cell header" />
    </div>
    <!-- Data rows -->
    <div v-for="r in rows" :key="r" class="skeleton-row">
      <div v-for="c in cols" :key="c" class="skeleton-cell" :style="{ width: c === 1 ? '60%' : '80%' }" />
    </div>
  </div>
</template>

<style scoped>
.skeleton-wrap {
  width: 100%;
}

.skeleton-header {
  display: flex;
  gap: 1rem;
  padding: 0.75rem 1rem;
  background: #f8fafc;
  border-radius: 6px 6px 0 0;
  border-bottom: 1px solid #e2e8f0;
}

.skeleton-row {
  display: flex;
  gap: 1rem;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.skeleton-cell {
  height: 14px;
  border-radius: 4px;
  background: linear-gradient(90deg, #e2e8f0 25%, #f1f5f9 50%, #e2e8f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s ease-in-out infinite;
  flex: 1;
}

.skeleton-cell.header {
  height: 12px;
  background: linear-gradient(90deg, #cbd5e1 25%, #e2e8f0 50%, #cbd5e1 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s ease-in-out infinite;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
