import type { AppNotification, DashboardStats, Paginated, Resource } from '~/types'

export function useDashboard() {
  const api = useApi()

  return {
    stats: () => api<DashboardStats>('/dashboard/stats'),
    notifications: () => api<Paginated<AppNotification>>('/notifications'),
    markAllRead: () => api<{ marked_read: number }>('/notifications/read-all', { method: 'POST' }),
    markRead: (id: number | string) => api<Resource<AppNotification>>(`/notifications/${id}/read`, { method: 'POST' }),
  }
}

export function formatMoney(value: number | string | null | undefined): string {
  const n = Number(value ?? 0)
  return n.toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 })
}
