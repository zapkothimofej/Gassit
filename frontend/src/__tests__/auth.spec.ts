import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../router', () => ({
  default: {
    push: vi.fn(),
  },
}))

vi.mock('../api/axios', () => ({
  default: {
    post: vi.fn(),
    get: vi.fn(),
  },
}))

import { useAuthStore } from '../stores/auth'
import router from '../router'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    sessionStorage.clear()
    vi.clearAllMocks()
  })

  it('isAuthenticated is false when no token in sessionStorage', () => {
    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(false)
  })

  it('isAuthenticated is true when token exists in sessionStorage', () => {
    sessionStorage.setItem('auth_token', 'test-token')
    const auth = useAuthStore()
    expect(auth.isAuthenticated).toBe(true)
  })

  it('logout clears token and user', () => {
    sessionStorage.setItem('auth_token', 'test-token')
    const auth = useAuthStore()
    auth.logout()

    expect(auth.token).toBeNull()
    expect(auth.user).toBeNull()
    expect(sessionStorage.getItem('auth_token')).toBeNull()
  })

  it('logout redirects to /login', () => {
    const auth = useAuthStore()
    auth.logout()
    expect(router.push).toHaveBeenCalledWith('/login')
  })

  it('role is null when no user is set', () => {
    const auth = useAuthStore()
    expect(auth.role).toBeNull()
  })

  it('parks is empty array when no user is set', () => {
    const auth = useAuthStore()
    expect(auth.parks).toEqual([])
  })
})
