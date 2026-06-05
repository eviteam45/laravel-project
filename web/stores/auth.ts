import { defineStore } from 'pinia'
import type { Credentials, RegisterPayload, Role, User } from '~/types'

export const useAuthStore = defineStore('auth', () => {
  const api = useApi()
  const token = useCookie<string | null>('token', { sameSite: 'lax' })
  const user = ref<User | null>(null)

  const isLoggedIn = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')

  function hasRole(...roles: Role[]): boolean {
    return !!user.value?.role && roles.includes(user.value.role)
  }

  async function fetchUser(): Promise<User | null> {
    if (!token.value) {
      user.value = null
      return null
    }
    try {
      user.value = await api<User>('/user')
    }
    catch {
      token.value = null
      user.value = null
    }
    return user.value
  }

  async function login(credentials: Credentials): Promise<User> {
    const res = await api<{ token: string, user: User }>('/login', { method: 'POST', body: credentials })
    clearNuxtData()
    token.value = res.token
    user.value = res.user
    return res.user
  }

  async function register(payload: RegisterPayload): Promise<User> {
    const res = await api<{ token: string, user: User }>('/register', { method: 'POST', body: payload })
    clearNuxtData()
    token.value = res.token
    user.value = res.user
    return res.user
  }

  async function logout(): Promise<void> {
    try {
      await api('/logout', { method: 'POST' })
    }
    finally {
      token.value = null
      user.value = null
      clearNuxtData()
    }
  }

  async function forgotPassword(email: string): Promise<void> {
    await api('/forgot-password', { method: 'POST', body: { email } })
  }

  async function resetPassword(payload: {
    token: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<void> {
    await api('/reset-password', { method: 'POST', body: payload })
  }

  return { token, user, isLoggedIn, isAdmin, hasRole, fetchUser, login, register, logout, forgotPassword, resetPassword }
})
