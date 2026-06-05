import type {
  ContractorOption,
  CustomerOption,
  DocumentFile,
  IncentiveApplication,
  MessageResponse,
  Paginated,
  Project,
  Resource,
} from '~/types'

interface ListParams {
  search?: string
  status?: string
  region?: string
  page?: number
  per_page?: number
  sort?: string
  dir?: 'asc' | 'desc'
}

export function useProjects() {
  const api = useApi()

  return {
    list: (params: ListParams = {}) => api<Paginated<Project>>('/projects', { params }),
    get: (id: number | string) => api<Resource<Project>>(`/projects/${id}`),
    create: (body: Record<string, unknown>) => api<Resource<Project>>('/projects', { method: 'POST', body }),
    update: (id: number | string, body: Record<string, unknown>) =>
      api<Resource<Project>>(`/projects/${id}`, { method: 'PUT', body }),
    remove: (id: number | string) => api<MessageResponse>(`/projects/${id}`, { method: 'DELETE' }),
    createApplication: (projectId: number | string) =>
      api<Resource<IncentiveApplication>>('/applications', { method: 'POST', body: { project_id: projectId } }),
    transition: (id: number | string, to: string, extra: Record<string, unknown> = {}) =>
      api<Resource<Project>>(`/projects/${id}/transition`, { method: 'POST', body: { to, ...extra } }),
    customerOptions: (search?: string) =>
      api<Resource<CustomerOption[]>>('/customers/options', { params: search ? { search } : {} }),
    contractorOptions: (search?: string) =>
      api<Resource<ContractorOption[]>>('/contractors/options', { params: search ? { search } : {} }),
    uploadDocument: (projectId: number | string, file: File, type: string) => {
      const form = new FormData()
      form.append('file', file)
      form.append('type', type)
      return api<Resource<DocumentFile>>(`/projects/${projectId}/documents`, { method: 'POST', body: form })
    },
    deleteDocument: (documentId: number | string) =>
      api<MessageResponse>(`/documents/${documentId}`, { method: 'DELETE' }),
  }
}

export const PROJECT_STATUSES = [
  'draft', 'submitted', 'in_review', 'approved', 'installed', 'closed', 'rejected',
] as const

export const REGIONS = ['North', 'South', 'East', 'West', 'Central'] as const

export const PROJECT_SORTABLE = ['name', 'status', 'capacity_kw', 'install_date', 'created_at'] as const

export const PROJECT_TRANSITIONS: Record<string, Record<string, string[]>> = {
  draft: { submitted: ['contractor'] },
  submitted: { in_review: ['admin'] },
  in_review: { approved: ['admin'], rejected: ['admin'] },
  approved: { installed: ['contractor', 'admin'] },
  installed: { closed: ['admin'] },
  rejected: {},
  closed: {},
}

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
