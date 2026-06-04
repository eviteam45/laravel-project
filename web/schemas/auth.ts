import { z } from 'zod'

export const loginSchema = z.object({
  email: z.string().min(1, 'Email is required').email('Enter a valid email'),
  password: z.string().min(1, 'Password is required'),
})

export const registerSchema = z
  .object({
    name: z.string().min(1, 'Name is required').max(255),
    email: z.string().min(1, 'Email is required').email('Enter a valid email').max(255),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string().min(1, 'Please confirm your password'),
    role: z.enum(['contractor', 'customer']),
    phone: z.string().max(50).optional(),
    company_name: z.string().max(255).optional(),
    address: z.string().max(255).optional(),
  })
  .refine(d => d.password === d.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  })
  .refine(d => d.role !== 'contractor' || !!d.company_name?.trim(), {
    message: 'Company name is required for contractors',
    path: ['company_name'],
  })
