import type { $Fetch } from 'nitropack'

/**
 * Returns a pre-configured $fetch instance pointed at the Laravel API.
 * Automatically attaches the bearer token (from the `token` cookie) and
 * the JSON Accept header so Laravel returns JSON validation errors.
 */
export function useApi(): $Fetch {
  const config = useRuntimeConfig()
  const token = useCookie<string | null>('token')

  return $fetch.create({
    baseURL: config.public.apiBase,
    headers: {
      Accept: 'application/json',
    },
    onRequest({ options }) {
      if (token.value) {
        options.headers.set('Authorization', `Bearer ${token.value}`)
      }
    },
  })
}
