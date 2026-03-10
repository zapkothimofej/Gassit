import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL as string,
  headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = sessionStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const auth = useAuthStore()
      auth.logout()
      return Promise.reject(error)
    }

    const { showToast } = useToastStore()

    // 422 validation errors — let caller handle inline
    if (error.response?.status === 422) {
      return Promise.reject(error)
    }

    if (!error.response) {
      showToast('Verbindungsfehler, bitte versuche es erneut', 'error')
    } else if (error.response.status >= 500) {
      const msg: string = (error.response.data as { message?: string })?.message ?? 'Ein Serverfehler ist aufgetreten'
      showToast(msg, 'error')
    }

    return Promise.reject(error)
  },
)

export default api
