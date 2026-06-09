import type {
  ApiError,
  ApplicationStep,
  DocumentFile,
  IncentiveApplication,
  MessageResponse,
  Paginated,
  Resource,
} from '~/types'
import { z } from 'zod'

export function useApplications() {
  const api = useApi()

  return {
    list: (params: Record<string, unknown> = {}) => api<Paginated<IncentiveApplication>>('/applications', { params }),
    get: (id: number | string) => api<Resource<IncentiveApplication>>(`/applications/${id}`),

    saveStep: (id: number | string, stepKey: string, fields: Record<string, unknown>, complete: boolean) =>
      api<Resource<ApplicationStep>>(`/applications/${id}/steps/${stepKey}`, {
        method: 'PUT',
        body: { data: fields, complete },
      }),

    uploadDocument: (id: number | string, file: File, type: string) => {
      const form = new FormData()
      form.append('file', file)
      form.append('type', type)
      return api<Resource<DocumentFile>>(`/applications/${id}/documents`, { method: 'POST', body: form })
    },

    deleteDocument: (documentId: number | string) =>
      api<MessageResponse>(`/documents/${documentId}`, { method: 'DELETE' }),

    submit: (id: number | string) =>
      api<Resource<IncentiveApplication>>(`/applications/${id}/submit`, { method: 'POST' }),

    transition: (id: number | string, to: string, extra: Record<string, unknown> = {}) =>
      api<Resource<IncentiveApplication>>(`/applications/${id}/transition`, { method: 'POST', body: { to, ...extra } }),
  }
}

export const APPLICATION_STATUSES = [
  'started', 'in_progress', 'submitted', 'under_review', 'reserved', 'paid', 'rejected', 'withdrawn',
] as const

export const APPLICATION_SORTABLE = ['status', 'submitted_at', 'created_at', 'updated_at'] as const

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

export function fieldErrors(e: unknown): Record<string, string> {
  const out: Record<string, string> = {}
  const errors = (e as ApiError)?.data?.errors ?? {}
  for (const [key, messages] of Object.entries(errors)) {
    out[key.replace(/^data\./, '')] = messages[0]
  }
  return out
}

const requiredString = (msg: string, max = 255) =>
  z.string({ required_error: msg, invalid_type_error: msg })
    .trim()
    .min(1, msg)
    .max(max, `Must be ${max} characters or fewer.`)

const requiredNumber = (msg: string, opts: { min?: number, int?: boolean } = {}) => {
  let base = z.number({ required_error: msg, invalid_type_error: msg })
  if (opts.int) base = base.int(msg)
  if (opts.min !== undefined) base = base.min(opts.min, `Must be ${opts.min} or more.`)
  return z.preprocess(
    v => (v === '' || v === null || v === undefined ? undefined : Number(v)),
    base,
  )
}

export const APPLICATION_STEP_SCHEMAS: Record<string, z.ZodTypeAny> = {
  eligibility: z.object({
    owns_property: z.boolean(),
    utility_provider: requiredString('Utility provider is required.'),
    average_monthly_bill: requiredNumber('Average monthly bill is required.', { min: 0 }),
  }),
  system: z.object({
    battery_oem: requiredString('OEM is required.'),
    battery_model: requiredString('Model is required.'),
    quantity: requiredNumber('Quantity must be at least 1.', { min: 1, int: true }),
    usable_capacity_kwh: requiredNumber('Usable capacity is required.', { min: 0 }),
  }),
  banking: z.object({
    account_holder_name: requiredString('Account holder is required.'),
    bank_name: requiredString('Bank name is required.'),
    routing_number: z.string({ required_error: 'Routing number must be 9 digits.' })
      .regex(/^\d{9}$/, 'Routing number must be 9 digits.'),
    account_number: z.string({ required_error: 'Account number must be 4–17 digits.' })
      .regex(/^\d{4,17}$/, 'Account number must be 4–17 digits.'),
    account_type: z.enum(['checking', 'savings'], {
      errorMap: () => ({ message: 'Select an account type.' }),
    }),
  }),
  review: z.object({
    accepted_terms: z.boolean().refine(v => v === true, 'You must confirm the information is accurate.'),
  }),
}

export function validateStep(key: string, data: Record<string, unknown>): Record<string, string> {
  const schema = APPLICATION_STEP_SCHEMAS[key]
  if (!schema) return {}

  const result = schema.safeParse(data)
  if (result.success) return {}

  const out: Record<string, string> = {}
  for (const issue of result.error.issues) {
    const field = String(issue.path[0] ?? '')
    if (field && !(field in out)) out[field] = issue.message
  }
  return out
}
