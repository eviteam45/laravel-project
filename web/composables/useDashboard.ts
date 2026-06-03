/**
 * Dashboard stats + notifications API.
 */
export function useDashboard() {
  const api = useApi()

  return {
    stats: () => api<any>('/dashboard/stats'),
    notifications: () => api<any>('/notifications'),
    markAllRead: () => api<any>('/notifications/read-all', { method: 'POST' }),
    markRead: (id: number | string) => api<any>(`/notifications/${id}/read`, { method: 'POST' }),
  }
}

export function formatMoney(value: number | string | null | undefined): string {
  const n = Number(value ?? 0)
  return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 })
}
