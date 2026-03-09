import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api/axios'
import echo from '../plugins/echo'
import { useAuthStore } from './auth'

export interface AppNotification {
  id: number
  type: string
  title: string
  body: string
  related_type: string | null
  related_id: number | null
  read_at: string | null
  created_at: string
}

export const useNotificationStore = defineStore('notifications', () => {
  const unreadCount = ref(0)
  const recent = ref<AppNotification[]>([])
  let echoChannel: ReturnType<typeof echo.private> | null = null
  let pollInterval: ReturnType<typeof setInterval> | null = null

  async function fetchUnreadCount() {
    try {
      const response = await api.get('/notifications/unread-count')
      unreadCount.value = response.data.count ?? 0
    } catch {
      // ignore
    }
  }

  async function fetchRecent() {
    try {
      const response = await api.get('/notifications', { params: { per_page: 5 } })
      const data = response.data
      recent.value = Array.isArray(data) ? data : (data.data ?? [])
    } catch {
      // ignore
    }
  }

  async function markRead(id: number) {
    try {
      await api.post(`/notifications/${id}/read`)
      const n = recent.value.find((x) => x.id === id)
      if (n) n.read_at = new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch {
      // ignore
    }
  }

  async function markAllRead() {
    try {
      await api.post('/notifications/read-all')
      recent.value.forEach((n) => { n.read_at = n.read_at ?? new Date().toISOString() })
      unreadCount.value = 0
    } catch {
      // ignore
    }
  }

  function subscribeToEcho(userId: number) {
    if (echoChannel) return

    echoChannel = echo.private(`App.User.${userId}`)
    echoChannel.listen('.notification.created', (payload: AppNotification) => {
      recent.value.unshift(payload)
      if (recent.value.length > 5) recent.value.pop()
      unreadCount.value += 1
    })
  }

  function unsubscribeFromEcho() {
    if (echoChannel) {
      echo.leave(`App.User.${useAuthStore().user?.id}`)
      echoChannel = null
    }
  }

  function startPolling() {
    fetchUnreadCount()
    fetchRecent()

    const auth = useAuthStore()
    if (auth.user?.id) {
      subscribeToEcho(auth.user.id)
    }

    // Keep a fallback poll every 5 minutes in case WS drops
    if (!pollInterval) {
      pollInterval = setInterval(() => {
        fetchUnreadCount()
      }, 300_000)
    }
  }

  function stopPolling() {
    unsubscribeFromEcho()
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }

  return {
    unreadCount,
    recent,
    fetchUnreadCount,
    fetchRecent,
    markRead,
    markAllRead,
    startPolling,
    stopPolling,
  }
})
