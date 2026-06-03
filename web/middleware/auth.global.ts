/**
 * Global auth guard, runs on every route change (server + client).
 *
 *  - `/`            → /dashboard if logged in, otherwise /login
 *  - protected page → /login if not logged in
 *  - /login,/register → /dashboard if already logged in
 *
 * "Logged in" is determined by the presence of the Sanctum token cookie.
 */
export default defineNuxtRouteMiddleware((to) => {
  const { isLoggedIn } = useAuth()
  const guestOnly = ['/login', '/register']

  // Entry point: route the user based on auth state.
  if (to.path === '/') {
    return navigateTo(isLoggedIn.value ? '/dashboard' : '/login')
  }

  // Protected pages require authentication.
  if (!isLoggedIn.value && !guestOnly.includes(to.path)) {
    return navigateTo('/login')
  }

  // Authenticated users shouldn't see the login/register pages.
  if (isLoggedIn.value && guestOnly.includes(to.path)) {
    return navigateTo('/dashboard')
  }
})
