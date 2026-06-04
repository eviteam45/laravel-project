import type { $Fetch } from 'nitropack'

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
