import type { Paginated, Resource, Role, User } from '~/types'

export function useUsers() {
  const api = useApi()

  return {
    list: (params: Record<string, unknown> = {}) => api<Paginated<User>>('/users', { params }),
    updateRole: (id: number | string, role: Role) =>
      api<Resource<User>>(`/users/${id}/role`, { method: 'PATCH', body: { role } }),
  }
}

export const ROLES: Role[] = ['admin', 'contractor', 'customer']
