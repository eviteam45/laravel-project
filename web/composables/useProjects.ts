interface ListParams {
  search?: string
  status?: string
  region?: string
  page?: number
  per_page?: number
  sort?: string
  dir?: 'asc' | 'desc'
}

/**
 * Thin wrapper around the Projects + Applications API endpoints.
 */
export function useProjects() {
  const api = useApi()

  return {
    list: (params: ListParams = {}) => api<any>('/projects', { params }),
    get: (id: number | string) => api<any>(`/projects/${id}`),
    create: (body: Record<string, any>) => api<any>('/projects', { method: 'POST', body }),
    update: (id: number | string, body: Record<string, any>) =>
      api<any>(`/projects/${id}`, { method: 'PUT', body }),
    remove: (id: number | string) => api<any>(`/projects/${id}`, { method: 'DELETE' }),
    createApplication: (projectId: number | string) =>
      api<any>('/applications', { method: 'POST', body: { project_id: projectId } }),
    transition: (id: number | string, to: string, extra: Record<string, any> = {}) =>
      api<any>(`/projects/${id}/transition`, { method: 'POST', body: { to, ...extra } }),
    customerOptions: (search?: string) =>
      api<any>('/customers/options', { params: search ? { search } : {} }),
    contractorOptions: (search?: string) =>
      api<any>('/contractors/options', { params: search ? { search } : {} }),
    uploadDocument: (projectId: number | string, file: File, type: string) => {
      const form = new FormData()
      form.append('file', file)
      form.append('type', type)
      return api<any>(`/projects/${projectId}/documents`, { method: 'POST', body: form })
    },
    deleteDocument: (documentId: number | string) =>
      api<any>(`/documents/${documentId}`, { method: 'DELETE' }),
  }
}

export const PROJECT_STATUSES = [
  'draft', 'submitted', 'in_review', 'approved', 'installed', 'closed', 'rejected',
] as const

/** Common contractor regions (region filter lives on the contractor). */
export const REGIONS = ['North', 'South', 'East', 'West', 'Central'] as const

/** Sort columns the projects index whitelists (must match the API). */
export const PROJECT_SORTABLE = ['name', 'status', 'capacity_kw', 'install_date', 'created_at'] as const

/** Mirror of the server-side project status graph: from → { to: [roles] }. */
export const PROJECT_TRANSITIONS: Record<string, Record<string, string[]>> = {
  draft: { submitted: ['contractor'] },
  submitted: { in_review: ['admin'] },
  in_review: { approved: ['admin'], rejected: ['admin'] },
  approved: { installed: ['contractor', 'admin'] },
  installed: { closed: ['admin'] },
  rejected: {},
  closed: {},
}

/** Destinations a given role may move to from `status` (admin may do any edge). */
export function transitionsFor(
  graph: Record<string, Record<string, string[]>>,
  status: string | undefined,
  role: string | undefined,
): string[] {
  const edges = graph[status ?? ''] ?? {}
  return Object.entries(edges)
    .filter(([, roles]) => role === 'admin' || roles.includes(role ?? ''))
    .map(([to]) => to)
}
