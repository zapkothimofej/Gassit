<script setup lang="ts">
import { ref, watch } from 'vue'
import api from '../api/axios'

interface SentEmail {
  id: number
  recipient: string
  subject: string
  template?: { name: string }
  sent_at: string
  status: 'sent' | 'failed' | 'bounced'
}

interface PaginatedResponse {
  data: SentEmail[]
  meta?: { current_page: number; last_page: number; total: number }
  last_page?: number
  total?: number
}

const emails = ref<SentEmail[]>([])
const loading = ref(false)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)

const filterStatus = ref('')
const filterDateFrom = ref('')
const filterDateTo = ref('')

const STATUS_OPTIONS = [
  { value: '', label: 'All Statuses' },
  { value: 'sent', label: 'Sent' },
  { value: 'failed', label: 'Failed' },
  { value: 'bounced', label: 'Bounced' },
]

const STATUS_COLORS: Record<string, string> = {
  sent: '#22c55e',
  failed: '#ef4444',
  bounced: '#f59e0b',
}

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: currentPage.value }
    if (filterStatus.value) params.status = filterStatus.value
    if (filterDateFrom.value) params.date_from = filterDateFrom.value
    if (filterDateTo.value) params.date_to = filterDateTo.value
    const res = await api.get<PaginatedResponse | SentEmail[]>('/mail/sent', { params })
    const d = res.data
    if (Array.isArray(d)) {
      emails.value = d
      lastPage.value = 1
      total.value = d.length
    } else {
      emails.value = d.data
      const meta = d.meta ?? d
      lastPage.value = (meta as { last_page?: number }).last_page ?? 1
      total.value = (meta as { total?: number }).total ?? emails.value.length
    }
  } catch {
    emails.value = []
  } finally {
    loading.value = false
  }
}

load()
watch([filterStatus, filterDateFrom, filterDateTo], () => {
  currentPage.value = 1
  load()
})

function formatDate(dt: string) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('de-DE', { dateStyle: 'short', timeStyle: 'short' })
}
</script>

<template>
  <div class="sent">
    <div class="filter-bar">
      <select v-model="filterStatus" class="filter-select">
        <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <div class="date-range">
        <label class="date-label">From</label>
        <input v-model="filterDateFrom" type="date" class="filter-date" />
        <label class="date-label">To</label>
        <input v-model="filterDateTo" type="date" class="filter-date" />
      </div>
    </div>

    <div class="card">
      <table class="table">
        <thead>
          <tr>
            <th>Recipient</th>
            <th>Subject</th>
            <th>Template</th>
            <th>Sent At</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="5" class="empty">Loading…</td></tr>
          <tr v-else-if="!emails.length"><td colspan="5" class="empty">No sent emails found.</td></tr>
          <tr v-for="e in emails" :key="e.id">
            <td class="fw">{{ e.recipient }}</td>
            <td class="subject-cell">{{ e.subject }}</td>
            <td>{{ e.template?.name ?? '—' }}</td>
            <td>{{ formatDate(e.sent_at) }}</td>
            <td>
              <span class="badge" :style="{ background: STATUS_COLORS[e.status] ?? '#6b7280' }">
                {{ e.status }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="lastPage > 1" class="pagination">
      <button class="page-btn" :disabled="currentPage === 1" @click="currentPage--; load()">‹ Prev</button>
      <span class="page-info">Page {{ currentPage }} / {{ lastPage }} ({{ total }} total)</span>
      <button class="page-btn" :disabled="currentPage === lastPage" @click="currentPage++; load()">Next ›</button>
    </div>
  </div>
</template>

<style scoped>
.sent { display: flex; flex-direction: column; gap: 1rem; padding: 1.5rem; }
.filter-bar { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
.filter-select { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; outline: none; }
.date-range { display: flex; align-items: center; gap: 0.5rem; }
.date-label { font-size: 0.8rem; color: #64748b; }
.filter-date { border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.4rem 0.75rem; font-size: 0.875rem; outline: none; }
.card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; }
.table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.table th { background: #f8fafc; padding: 0.75rem 1rem; text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e2e8f0; }
.table td { padding: 0.75rem 1rem; border-bottom: 1px solid #f1f5f9; color: #334155; }
.table tr:last-child td { border-bottom: none; }
.fw { font-weight: 500; }
.subject-cell { max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.empty { text-align: center; color: #94a3b8; padding: 2rem; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; color: #fff; text-transform: capitalize; }
.pagination { display: flex; align-items: center; gap: 0.75rem; justify-content: flex-end; }
.page-btn { padding: 5px 12px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; font-size: 0.875rem; }
.page-btn:disabled { opacity: .4; cursor: default; }
.page-info { font-size: 0.875rem; color: #64748b; }
</style>
