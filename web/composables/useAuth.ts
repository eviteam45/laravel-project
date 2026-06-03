interface User {
  id: number
  name: string
  email: string
  role?: string
  status?: string
}

interface Credentials {
  email: string
  password: string
}

interface RegisterPayload extends Credentials {
  name: string
  password_confirmation: string
  role: 'contractor' | 'customer'
  phone?: string
  company_name?: string
  license_no?: string
  region?: string
  full_name?: string
  address?: string
}

/**
 * Token-based auth state and actions for the Laravel Sanctum API.
 * The token is persisted in a cookie so it survives reloads and is
 * available during SSR.
 */
export function useAuth() {
  const api = useApi()
  const token = useCookie<string | null>('token', { sameSite: 'lax' })
  const user = useState<User | null>('auth:user', () => null)

  const isLoggedIn = computed(() => !!token.value)

  async function fetchUser() {
    if (!token.value) {
      user.value = null
      return null
    }
    try {
      user.value = await api<User>('/user')
    }
    catch {
      // Token is invalid/expired — clear it.
      token.value = null
      user.value = null
    }
    return user.value
  }

  async function login(credentials: Credentials) {
    const { token: newToken, user: authUser } = await api<{ token: string, user: User }>('/login', {
      method: 'POST',
      body: credentials,
    })
    token.value = newToken
    user.value = authUser
    return authUser
  }

  async function register(payload: RegisterPayload) {
    const { token: newToken, user: authUser } = await api<{ token: string, user: User }>('/register', {
      method: 'POST',
      body: payload,
    })
    token.value = newToken
    user.value = authUser
    return authUser
  }

  async function logout() {
    try {
      await api('/logout', { method: 'POST' })
    }
    finally {
      token.value = null
      user.value = null
    }
  }

  return { user, token, isLoggedIn, fetchUser, login, register, logout }
}
