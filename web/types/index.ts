export type Role = 'admin' | 'contractor' | 'customer'

export interface User {
  id: number
  name: string
  email: string
  role?: Role
  status?: string
  last_login_at?: string | null
  created_at?: string
}

export interface Credentials {
  email: string
  password: string
}

export interface RegisterPayload extends Credentials {
  name: string
  password_confirmation: string
  role: 'contractor' | 'customer'
  phone?: string
  company_name?: string
  license_no?: string
  region?: string
  full_name?: string
  address?: string
}

export interface Paginated<T> {
  data: T[]
  links: Record<string, string | null>
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
