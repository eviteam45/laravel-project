export function useAudit() {
  const api = useApi()

  return {
    list: (params: Record<string, any> = {}) => api<any>('/audit-logs', { params }),
  }
}
