export function mapServerErrors(errors: Record<string, string[]>): Record<string, string> {
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0]]),
  )
}
