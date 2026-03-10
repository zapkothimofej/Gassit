import { ref } from 'vue'

interface Toast {
  id: number
  message: string
  type: 'success' | 'error' | 'info'
}

let nextId = 1
const toasts = ref<Toast[]>([])

function showToast(message: string, type: Toast['type'] = 'info', duration = 4000) {
  const id = nextId++
  toasts.value.push({ id, message, type })
  setTimeout(() => {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }, duration)
}

export function useToastStore() {
  return { toasts, showToast }
}
