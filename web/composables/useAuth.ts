import { storeToRefs } from 'pinia'

export function useAuth() {
  const store = useAuthStore()
  const { user, token, isLoggedIn, isAdmin } = storeToRefs(store)

  return {
    user,
    token,
    isLoggedIn,
    isAdmin,
    hasRole: store.hasRole,
    fetchUser: store.fetchUser,
    login: store.login,
    register: store.register,
    logout: store.logout,
  }
}
