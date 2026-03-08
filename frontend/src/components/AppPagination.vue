<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  currentPage: number
  totalPages: number
}>()

const emit = defineEmits<{
  (e: 'page-change', page: number): void
}>()

const pages = computed(() => {
  const all: (number | '…')[] = []
  const { currentPage: cur, totalPages: total } = props
  for (let i = 1; i <= total; i++) {
    if (i === 1 || i === total || (i >= cur - 2 && i <= cur + 2)) {
      all.push(i)
    } else if (all[all.length - 1] !== '…') {
      all.push('…')
    }
  }
  return all
})
</script>

<template>
  <div class="pagination">
    <button :disabled="currentPage <= 1" @click="emit('page-change', currentPage - 1)">‹</button>
    <template v-for="page in pages" :key="page">
      <span v-if="page === '…'" class="ellipsis">…</span>
      <button
        v-else
        :class="{ active: page === currentPage }"
        @click="emit('page-change', page as number)"
      >
        {{ page }}
      </button>
    </template>
    <button :disabled="currentPage >= totalPages" @click="emit('page-change', currentPage + 1)">›</button>
  </div>
</template>

<style scoped>
.pagination {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.875rem;
}

button {
  min-width: 32px;
  height: 32px;
  padding: 0 0.5rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  color: #374151;
  transition: background 0.1s;
}

button:hover:not(:disabled) {
  background: #f1f5f9;
}

button:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

button.active {
  background: #3b82f6;
  border-color: #3b82f6;
  color: #fff;
}

.ellipsis {
  padding: 0 0.25rem;
  color: #94a3b8;
}
</style>
