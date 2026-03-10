import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api/axios'
import router from '../router'

interface Park {
  id: number
  name: string
}

interface User {
  id: number
  name: string
  email: string
  role: string
  parks: Park[]
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(sessionStorage.getItem('auth_token'))
  const user = ref<User | null>(null)

  const role = computed(() => user.value?.role ?? null)
  const parks = computed(() => user.value?.parks ?? [])
  const isAuthenticated = computed(() => !!token.value)

  async function login(email: string, password: string) {
    const response = await api.post('/auth/login', { email, password })
    const data = response.data

    if (data.requires_2fa) {
      return data
    }

    token.value = data.token
    sessionStorage.setItem('auth_token', data.token)
    user.value = data.user

    return data
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      const response = await api.get('/auth/me')
      user.value = response.data
    } catch {
      logout()
    }
  }

  function logout() {
    try {
      api.post('/auth/logout').catch(() => {})
    } catch {
      // ignore
    }
    token.value = null
    user.value = null
    sessionStorage.removeItem('auth_token')
    router.push('/login')
  }

  return { token, user, role, parks, isAuthenticated, login, logout, fetchUser }
})
