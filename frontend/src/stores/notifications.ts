import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api/axios'

export const useNotificationStore = defineStore('notifications', () => {
  const unreadCount = ref(0)

  async function fetchUnreadCount() {
    try {
      const response = await api.get('/notifications/unread-count')
      unreadCount.value = response.data.count ?? 0
    } catch {
      // ignore
    }
  }

  return { unreadCount, fetchUnreadCount }
})
