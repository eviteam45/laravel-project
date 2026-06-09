export default defineNuxtPlugin(() => {
  const apiBase = useRuntimeConfig().public.apiBase

  if (apiBase === 'http://localhost:8000/api' && !import.meta.dev) {
    const message = 'NUXT_PUBLIC_API_BASE is not set — refusing to fall back to http://localhost:8000/api in production.'

    if (import.meta.server) {
      throw createError({ statusCode: 500, statusMessage: message, fatal: true })
    }

    console.error('[config]', message)
  }
})
