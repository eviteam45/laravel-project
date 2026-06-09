import type { AuditLog, CursorPaginated } from '~/types'

export function useAudit() {
  const api = useApi()

  return {
    list: (params: Record<string, unknown> = {}) => api<CursorPaginated<AuditLog>>('/audit-logs', { params }),
  }
}
