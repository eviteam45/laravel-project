/**
 * API helpers for the incentive-application multi-step wizard.
 */
export function useApplications() {
  const api = useApi()

  return {
    list: (params: Record<string, any> = {}) => api<any>('/applications', { params }),
    get: (id: number | string) => api<any>(`/applications/${id}`),

    saveStep: (id: number | string, stepKey: string, fields: Record<string, any>, complete: boolean) =>
      api<any>(`/applications/${id}/steps/${stepKey}`, {
        method: 'PUT',
        body: { data: fields, complete },
      }),

    uploadDocument: (id: number | string, file: File, type: string) => {
      const form = new FormData()
      form.append('file', file)
      form.append('type', type)
      return api<any>(`/applications/${id}/documents`, { method: 'POST', body: form })
    },

    deleteDocument: (documentId: number | string) =>
      api<any>(`/documents/${documentId}`, { method: 'DELETE' }),

    submit: (id: number | string) =>
      api<any>(`/applications/${id}/submit`, { method: 'POST' }),

    transition: (id: number | string, to: string, extra: Record<string, any> = {}) =>
      api<any>(`/applications/${id}/transition`, { method: 'POST', body: { to, ...extra } }),
  }
}

/** All application statuses (for filters). */
export const APPLICATION_STATUSES = [
  'started', 'in_progress', 'submitted', 'under_review', 'reserved', 'paid', 'rejected', 'withdrawn',
] as const

/** Sort columns the applications index whitelists (must match the API). */
export const APPLICATION_SORTABLE = ['status', 'submitted_at', 'created_at', 'updated_at'] as const

/** Mirror of the server-side application status graph: from → { to: [roles] }. */
export const APPLICATION_TRANSITIONS: Record<string, Record<string, string[]>> = {
  started: { in_progress: ['contractor', 'customer'], withdrawn: ['customer', 'contractor'] },
  in_progress: { submitted: ['contractor', 'customer'], withdrawn: ['customer', 'contractor'] },
  submitted: { under_review: ['admin'], withdrawn: ['customer'] },
  under_review: { reserved: ['admin'], rejected: ['admin'], withdrawn: ['customer'] },
  reserved: { paid: ['admin'] },
  rejected: {},
  paid: {},
  withdrawn: {},
}

/** Strip Laravel's `data.` prefix from step validation error keys. */
export function fieldErrors(e: any): Record<string, string> {
  const out: Record<string, string> = {}
  const errors = e?.data?.errors ?? {}
  for (const [key, messages] of Object.entries(errors)) {
    out[key.replace(/^data\./, '')] = (messages as string[])[0]
  }
  return out
}
