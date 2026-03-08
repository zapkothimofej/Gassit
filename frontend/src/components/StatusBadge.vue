<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const GREEN  = new Set(['active', 'paid', 'free', 'signed', 'resolved', 'received', 'tenant', 'completed'])
const YELLOW = new Set(['in_progress', 'reserved', 'pending', 'awaiting_signature', 'in_repair', 'repair_ordered', 'in_assessment', 'waiting', 'sent', 'partially_returned'])
const RED    = new Set(['overdue', 'blacklisted', 'damage', 'terminated_by_customer', 'terminated_by_lfg', 'troublemaker', 'debtor', 'forfeited', 'cancelled', 'declined'])

const color = computed(() => {
  if (GREEN.has(props.status))  return 'green'
  if (YELLOW.has(props.status)) return 'yellow'
  if (RED.has(props.status))    return 'red'
  return 'gray'
})

const label = computed(() => props.status.replace(/_/g, ' '))
</script>

<template>
  <span :class="['badge', `badge-${color}`]">{{ label }}</span>
</template>

<style scoped>
.badge {
  display: inline-block;
  padding: 0.2rem 0.55rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
  white-space: nowrap;
}

.badge-green  { background: #dcfce7; color: #166534; }
.badge-yellow { background: #fef9c3; color: #854d0e; }
.badge-red    { background: #fee2e2; color: #991b1b; }
.badge-gray   { background: #f1f5f9; color: #475569; }
</style>
