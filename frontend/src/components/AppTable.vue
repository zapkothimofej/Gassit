<script setup lang="ts">
export interface Column {
  key: string
  label: string
  sortable?: boolean
}

defineProps<{
  columns: Column[]
  rows: Record<string, unknown>[]
  sortKey?: string
  sortDir?: 'asc' | 'desc'
}>()

const emit = defineEmits<{
  (e: 'sort', key: string): void
  (e: 'row-click', row: Record<string, unknown>): void
}>()
</script>

<template>
  <div class="table-wrapper">
    <table class="app-table">
      <thead>
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            :class="{ sortable: col.sortable }"
            @click="col.sortable && emit('sort', col.key)"
          >
            {{ col.label }}
            <span v-if="col.sortable && sortKey === col.key">
              {{ sortDir === 'asc' ? '▲' : '▼' }}
            </span>
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-if="rows.length === 0">
          <tr>
            <td :colspan="columns.length" class="empty-state">
              <slot name="empty">No data available.</slot>
            </td>
          </tr>
        </template>
        <tr
          v-for="(row, idx) in rows"
          :key="idx"
          class="table-row"
          @click="emit('row-click', row)"
        >
          <td v-for="col in columns" :key="col.key">
            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
              {{ row[col.key] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.table-wrapper {
  overflow-x: auto;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
}

.app-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}

thead th {
  background: #f8fafc;
  padding: 0.75rem 1rem;
  text-align: left;
  font-weight: 600;
  color: #475569;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}

thead th.sortable {
  cursor: pointer;
  user-select: none;
}

thead th.sortable:hover {
  background: #f1f5f9;
}

tbody td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  color: #374151;
}

.table-row {
  cursor: pointer;
  transition: background 0.1s;
}

.table-row:hover {
  background: #f8fafc;
}

.empty-state {
  text-align: center;
  color: #94a3b8;
  padding: 2rem;
}
</style>
