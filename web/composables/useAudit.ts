import type { AuditLog, Paginated } from '~/types'

export function useAudit() {
  const api = useApi()

  return {
    list: (params: Record<string, unknown> = {}) => api<Paginated<AuditLog>>('/audit-logs', { params }),
  }
}
