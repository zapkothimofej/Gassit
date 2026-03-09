<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import AppSidebar from '../components/AppSidebar.vue'
import AppTopBar from '../components/AppTopBar.vue'
import { useNotificationStore } from '../stores/notifications'
import { useToastStore } from '../stores/toast'

const notificationStore = useNotificationStore()
const { toasts } = useToastStore()

onMounted(() => {
  notificationStore.startPolling()
})
</script>

<template>
  <div class="app-layout">
    <AppSidebar />
    <div class="app-main">
      <AppTopBar />
      <main class="app-content">
        <RouterView />
      </main>
    </div>

    <!-- Global toasts -->
    <Teleport to="body">
      <div class="toast-container">
        <div
          v-for="t in toasts"
          :key="t.id"
          class="global-toast"
          :class="t.type"
        >
          {{ t.message }}
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.app-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.app-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.app-content {
  flex: 1;
  overflow-y: auto;
  padding: 1.5rem;
}

.toast-container {
  position: fixed;
  bottom: 1.5rem;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  z-index: 9999;
  pointer-events: none;
  align-items: center;
}

.global-toast {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
  animation: toast-in 0.2s ease;
}

.global-toast.success { background: #22c55e; color: #fff; }
.global-toast.error { background: #ef4444; color: #fff; }
.global-toast.info { background: #3b82f6; color: #fff; }

@keyframes toast-in {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
