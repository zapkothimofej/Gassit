<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore, type AppNotification } from '../stores/notifications'
import AppPagination from '../components/AppPagination.vue'
import AppButton from '../components/AppButton.vue'
import api from '../api/axios'

const router = useRouter()
const notificationStore = useNotificationStore()

const notifications = ref<AppNotification[]>([])
const loading = ref(false)
const currentPage = ref(1)
const totalPages = ref(1)
const filterUnread = ref<'' | 'unread' | 'read'>('')

async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: currentPage.value, per_page: 15 }
    if (filterUnread.value === 'unread') params.unread = 1
    if (filterUnread.value === 'read') params.read = 1
    const res = await api.get('/notifications', { params })
    const data = res.data
    if (Array.isArray(data)) {
      notifications.value = data
    } else {
      notifications.value = data.data ?? []
      totalPages.value = data.last_page ?? 1
    }
  } catch {
    //
  } finally {
    loading.value = false
  }
}

function onPageChange(page: number) {
  currentPage.value = page
  load()
}

function onFilterChange() {
  currentPage.value = 1
  load()
}

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const mins = Math.floor(diff / 60_000)
  if (mins < 1) return 'gerade eben'
  if (mins < 60) return `vor ${mins} Min.`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `vor ${hours} Std.`
  const days = Math.floor(hours / 24)
  return `vor ${days} Tag${days > 1 ? 'en' : ''}`
}

function entityRoute(n: AppNotification): string | null {
  if (!n.related_type || !n.related_id) return null
  const map: Record<string, string> = {
    Task: '/tasks',
    Invoice: '/invoices',
    Application: '/applications',
    WaitingList: '/waiting-list',
    DunningRecord: '/dunning',
    Customer: '/customers',
    Contract: '/contracts',
  }
  const base = map[n.related_type]
  if (!base) return null
  if (['Task', 'Invoice', 'Application', 'Customer', 'Contract'].includes(n.related_type)) {
    return `${base}/${n.related_id}`
  }
  return base
}

async function clickNotification(n: AppNotification) {
  if (!n.read_at) {
    try {
      await api.post(`/notifications/${n.id}/read`)
      n.read_at = new Date().toISOString()
      notificationStore.unreadCount = Math.max(0, notificationStore.unreadCount - 1)
    } catch { /* ignore */ }
  }
  const route = entityRoute(n)
  if (route) router.push(route)
}

async function markAllRead() {
  await notificationStore.markAllRead()
  notifications.value.forEach((n) => { n.read_at = n.read_at ?? new Date().toISOString() })
}

function typeLabel(type: string): string {
  const labels: Record<string, string> = {
    task_assigned: 'Aufgabe',
    invoice_overdue: 'Rechnung',
    application_assigned: 'Anfrage',
    waiting_list_available: 'Warteliste',
    dunning_escalated: 'Mahnung',
  }
  return labels[type] ?? type
}

function typeBadgeClass(type: string): string {
  const classes: Record<string, string> = {
    task_assigned: 'badge-blue',
    invoice_overdue: 'badge-red',
    application_assigned: 'badge-green',
    waiting_list_available: 'badge-yellow',
    dunning_escalated: 'badge-orange',
  }
  return classes[type] ?? 'badge-gray'
}

onMounted(load)
</script>

<template>
  <div class="notifications-page">
    <div class="page-header">
      <h1 class="page-title">Benachrichtigungen</h1>
      <AppButton variant="secondary" @click="markAllRead">Alle als gelesen markieren</AppButton>
    </div>

    <div class="filter-bar">
      <label class="filter-label">Anzeigen:</label>
      <select v-model="filterUnread" class="filter-select" @change="onFilterChange">
        <option value="">Alle</option>
        <option value="unread">Ungelesen</option>
        <option value="read">Gelesen</option>
      </select>
    </div>

    <div v-if="loading" class="loading-state">Laden...</div>

    <div v-else-if="notifications.length === 0" class="empty-state">
      Keine Benachrichtigungen vorhanden.
    </div>

    <ul v-else class="notif-list">
      <li
        v-for="n in notifications"
        :key="n.id"
        class="notif-item"
        :class="{ unread: !n.read_at, clickable: !!entityRoute(n) }"
        @click="clickNotification(n)"
      >
        <div class="notif-left">
          <span class="type-badge" :class="typeBadgeClass(n.type)">
            {{ typeLabel(n.type) }}
          </span>
          <div class="notif-content">
            <div class="notif-title">{{ n.title }}</div>
            <div class="notif-body">{{ n.body }}</div>
          </div>
        </div>
        <div class="notif-right">
          <span class="notif-time">{{ timeAgo(n.created_at) }}</span>
          <span v-if="!n.read_at" class="unread-dot" title="Ungelesen" />
        </div>
      </li>
    </ul>

    <AppPagination
      v-if="totalPages > 1"
      :current-page="currentPage"
      :total-pages="totalPages"
      @page-change="onPageChange"
    />
  </div>
</template>

<style scoped>
.notifications-page {
  max-width: 800px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.25rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.filter-bar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.filter-label {
  font-size: 0.875rem;
  color: #64748b;
}

.filter-select {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
  background: #fff;
  cursor: pointer;
}

.loading-state,
.empty-state {
  text-align: center;
  padding: 3rem;
  color: #94a3b8;
  font-size: 0.875rem;
}

.notif-list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow: hidden;
  margin-bottom: 1.5rem;
}

.notif-item {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 1rem 1.25rem;
  border-bottom: 1px solid #f1f5f9;
  gap: 1rem;
  transition: background 0.1s;
}

.notif-item:last-child {
  border-bottom: none;
}

.notif-item.clickable {
  cursor: pointer;
}

.notif-item.clickable:hover {
  background: #f8fafc;
}

.notif-item.unread {
  background: #eff6ff;
}

.notif-item.unread:hover {
  background: #dbeafe;
}

.notif-left {
  display: flex;
  align-items: flex-start;
  gap: 0.875rem;
  flex: 1;
  min-width: 0;
}

.type-badge {
  flex-shrink: 0;
  font-size: 0.6875rem;
  font-weight: 600;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  margin-top: 2px;
}

.badge-blue { background: #dbeafe; color: #1d4ed8; }
.badge-red { background: #fee2e2; color: #b91c1c; }
.badge-green { background: #dcfce7; color: #15803d; }
.badge-yellow { background: #fef9c3; color: #a16207; }
.badge-orange { background: #ffedd5; color: #c2410c; }
.badge-gray { background: #f1f5f9; color: #64748b; }

.notif-content {
  min-width: 0;
}

.notif-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 2px;
}

.notif-body {
  font-size: 0.8125rem;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.notif-right {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.375rem;
  flex-shrink: 0;
}

.notif-time {
  font-size: 0.75rem;
  color: #94a3b8;
  white-space: nowrap;
}

.unread-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #3b82f6;
}
</style>
