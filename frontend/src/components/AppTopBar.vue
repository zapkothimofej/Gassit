<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'

const auth = useAuthStore()
const notificationStore = useNotificationStore()

const selectedParkId = ref<number | null>(
  auth.parks[0]?.id ?? null,
)
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
      <button class="icon-btn notification-btn" title="Notifications">
        🔔
        <span v-if="notificationStore.unreadCount > 0" class="badge">
          {{ notificationStore.unreadCount }}
        </span>
      </button>

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
