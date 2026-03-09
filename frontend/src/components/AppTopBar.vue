<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'

const auth = useAuthStore()
const notificationStore = useNotificationStore()
const router = useRouter()

const selectedParkId = ref<number | null>(
  auth.parks[0]?.id ?? null,
)

const dropdownOpen = ref(false)

function toggleDropdown() {
  if (!dropdownOpen.value) {
    notificationStore.fetchRecent()
  }
  dropdownOpen.value = !dropdownOpen.value
}

function closeDropdown() {
  dropdownOpen.value = false
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

function entityRoute(n: { related_type: string | null; related_id: number | null }): string | null {
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

async function clickNotification(n: { id: number; related_type: string | null; related_id: number | null; read_at: string | null }) {
  if (!n.read_at) {
    await notificationStore.markRead(n.id)
  }
  const route = entityRoute(n)
  if (route) {
    router.push(route)
  }
  closeDropdown()
}

function handleOutsideClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (!target.closest('.notification-wrapper')) {
    dropdownOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', handleOutsideClick))
onUnmounted(() => document.removeEventListener('click', handleOutsideClick))
</script>

<template>
  <header class="topbar">
    <div class="topbar-left">
      <select
        v-if="auth.parks.length > 0"
        v-model="selectedParkId"
        class="park-selector"
      >
        <option
          v-for="park in auth.parks"
          :key="park.id"
          :value="park.id"
        >
          {{ park.name }}
        </option>
      </select>
    </div>

    <div class="topbar-right">
      <div class="notification-wrapper">
        <button class="icon-btn notification-btn" title="Benachrichtigungen" @click.stop="toggleDropdown">
          🔔
          <span v-if="notificationStore.unreadCount > 0" class="badge">
            {{ notificationStore.unreadCount > 99 ? '99+' : notificationStore.unreadCount }}
          </span>
        </button>

        <div v-if="dropdownOpen" class="notif-dropdown">
          <div class="notif-header">
            <span class="notif-title">Benachrichtigungen</span>
            <button class="mark-all-btn" @click="notificationStore.markAllRead()">Alle gelesen</button>
          </div>

          <div v-if="notificationStore.recent.length === 0" class="notif-empty">
            Keine Benachrichtigungen
          </div>

          <ul v-else class="notif-list">
            <li
              v-for="n in notificationStore.recent"
              :key="n.id"
              class="notif-item"
              :class="{ unread: !n.read_at }"
              @click="clickNotification(n)"
            >
              <div class="notif-item-title">{{ n.title }}</div>
              <div class="notif-item-body">{{ n.body }}</div>
              <div class="notif-item-time">{{ timeAgo(n.created_at) }}</div>
            </li>
          </ul>

          <div class="notif-footer">
            <router-link to="/notifications" class="view-all-link" @click="closeDropdown">
              Alle anzeigen
            </router-link>
          </div>
        </div>
      </div>

      <div class="user-info">
        <div class="avatar">
          {{ auth.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
        </div>
        <span class="user-name">{{ auth.user?.name }}</span>
      </div>

      <button class="logout-btn" @click="auth.logout()">Logout</button>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  height: 56px;
  background: #fff;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 1.5rem;
  flex-shrink: 0;
}

.topbar-left,
.topbar-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.park-selector {
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
  background: #f8fafc;
  cursor: pointer;
}

.icon-btn {
  background: none;
  border: none;
  font-size: 1.25rem;
  cursor: pointer;
  position: relative;
  padding: 0.25rem;
}

.badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: #ef4444;
  color: #fff;
  font-size: 0.65rem;
  font-weight: 700;
  border-radius: 9999px;
  min-width: 16px;
  height: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
}

.notification-wrapper {
  position: relative;
}

.notif-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 340px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  z-index: 1000;
}

.notif-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.notif-title {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1e293b;
}

.mark-all-btn {
  background: none;
  border: none;
  font-size: 0.75rem;
  color: #3b82f6;
  cursor: pointer;
  padding: 0;
}

.mark-all-btn:hover {
  text-decoration: underline;
}

.notif-empty {
  padding: 1.5rem 1rem;
  text-align: center;
  font-size: 0.875rem;
  color: #94a3b8;
}

.notif-list {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 320px;
  overflow-y: auto;
}

.notif-item {
  padding: 0.75rem 1rem;
  cursor: pointer;
  border-bottom: 1px solid #f8fafc;
  transition: background 0.1s;
}

.notif-item:hover {
  background: #f8fafc;
}

.notif-item.unread {
  background: #eff6ff;
}

.notif-item.unread:hover {
  background: #dbeafe;
}

.notif-item-title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 2px;
}

.notif-item-body {
  font-size: 0.75rem;
  color: #64748b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 4px;
}

.notif-item-time {
  font-size: 0.6875rem;
  color: #94a3b8;
}

.notif-footer {
  padding: 0.625rem 1rem;
  text-align: center;
  border-top: 1px solid #f1f5f9;
}

.view-all-link {
  font-size: 0.8125rem;
  color: #3b82f6;
  text-decoration: none;
}

.view-all-link:hover {
  text-decoration: underline;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.avatar {
  width: 32px;
  height: 32px;
  background: #3b82f6;
  color: #fff;
  border-radius: 9999px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  font-weight: 600;
}

.user-name {
  font-size: 0.875rem;
  color: #374151;
}

.logout-btn {
  background: none;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
  cursor: pointer;
  color: #64748b;
  transition: background 0.15s;
}

.logout-btn:hover {
  background: #f1f5f9;
}
</style>
