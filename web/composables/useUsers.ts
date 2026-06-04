import type { Role } from '~/types'

export function useUsers() {
  const api = useApi()

  return {
    list: (params: Record<string, any> = {}) => api<any>('/users', { params }),
    updateRole: (id: number | string, role: Role) =>
      api<any>(`/users/${id}/role`, { method: 'PATCH', body: { role } }),
  }
}

export const ROLES: Role[] = ['admin', 'contractor', 'customer']
