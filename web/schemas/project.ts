import { z } from 'zod'

const optionalNumber = z.preprocess(
  v => (v === '' || v === null || v === undefined ? null : Number(v)),
  z.number().min(0).max(100000).nullable(),
)

export const projectSchema = z.object({
  name: z.string().min(1, 'Name is required').max(255),
  contractor_id: z.number().int().positive().nullable().optional(),
  customer_id: z.number({ message: 'Select a customer' }).int().positive('Select a customer'),
  address: z.string().max(255).nullable().optional(),
  capacity_kw: optionalNumber,
  install_date: z.string().nullable().optional(),
})

export type ProjectFormValues = z.infer<typeof projectSchema>
