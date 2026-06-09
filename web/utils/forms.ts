import type { ApiError } from '~/types'

export function mapServerErrors(errors: Record<string, string[]>): Record<string, string> {
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0]]),
  )
}

/**
 * Best human-readable message from a failed API call: the first field-level
 * validation error if present, otherwise the top-level message, otherwise a
 * caller-supplied fallback.
 */
export function apiErrorMessage(e: unknown, fallback = 'Something went wrong.'): string {
  const err = e as ApiError
  const first = err?.data?.errors ? Object.values(err.data.errors)[0] : undefined
  if (first?.length) return first[0]
  return err?.data?.message ?? fallback
}

/**
 * For VeeValidate forms: push server field errors into the form, or return the
 * top-level message (null when there was a field-level error to surface).
 */
export function applyServerErrors(e: unknown, setErrors: (errors: Record<string, string>) => void): string | null {
  const err = e as ApiError
  if (err?.data?.errors) {
    setErrors(mapServerErrors(err.data.errors))
    return null
  }
  return err?.data?.message ?? null
}
