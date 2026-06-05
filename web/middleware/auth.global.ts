import type { Role } from '~/types'

export default defineNuxtRouteMiddleware(async (to) => {
  const auth = useAuth()
  const guestOnly = ['/login', '/register', '/forgot-password', '/reset-password']

  if (auth.isLoggedIn.value && !auth.user.value) {
    await auth.fetchUser()
  }

  if (to.path === '/') {
    return navigateTo(auth.isLoggedIn.value ? '/dashboard' : '/login')
  }

  if (!auth.isLoggedIn.value && !guestOnly.includes(to.path)) {
    return navigateTo('/login')
  }

  if (auth.isLoggedIn.value && guestOnly.includes(to.path)) {
    return navigateTo('/dashboard')
  }

  const roles = to.meta.roles as Role[] | undefined
  if (roles?.length && !auth.hasRole(...roles)) {
    return navigateTo('/dashboard')
  }
})
