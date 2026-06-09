export type Role = 'admin' | 'contractor' | 'customer'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  status?: string
  last_login_at?: string | null
  created_at: string
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

export interface Resource<T> {
  data: T
}

export interface CursorPaginated<T> {
  data: T[]
  meta: {
    path: string
    per_page: number
    next_cursor: string | null
    prev_cursor: string | null
  }
}

export interface MessageResponse {
  message: string
}

/** Shape of a failed `$fetch` call against the API (Laravel error envelope). */
export interface ApiError {
  status?: number
  statusCode?: number
  response?: { status?: number }
  data?: {
    message?: string
    errors?: Record<string, string[]>
  }
}

export type ProjectStatus
  = 'draft' | 'submitted' | 'in_review' | 'approved' | 'installed' | 'closed' | 'rejected'

export type ApplicationStatus
  = 'started' | 'in_progress' | 'submitted' | 'under_review' | 'reserved' | 'paid' | 'rejected' | 'withdrawn'

export type PaymentStatus = 'pending' | 'scheduled' | 'paid' | 'failed'

export interface UserMini {
  id: number
  name: string
  email?: string
}

export interface Contractor {
  id: number
  company_name: string
  license_no?: string | null
  phone?: string | null
  region?: string | null
  status?: string
  projects_count?: number
  user?: UserMini
  created_at: string
}

export interface Customer {
  id: number
  full_name: string
  address?: string | null
  phone?: string | null
  account_email?: string | null
  projects_count?: number
  user?: UserMini
  created_at: string
}

export interface BatterySystem {
  id: number
  oem: string
  model: string
  quantity: number
  usable_capacity_kwh: number | string
}

export interface DocumentFile {
  id: number
  type: string
  file_name: string
  uploaded_by: number | null
  created_at: string
  download_url: string
}

export interface ApplicationStep {
  id: number
  step_key: string
  fields: Record<string, unknown> | null
  completed_at: string | null
  is_complete: boolean
}

export interface IncentiveApplication {
  id: number
  project_id: number
  status: ApplicationStatus
  current_step: string | null
  submitted_at: string | null
  incentive_amount: number | string | null
  created_at: string
  updated_at?: string
  step_keys?: string[]
  steps?: ApplicationStep[]
  documents?: DocumentFile[]
  project?: Project
}

export interface Project {
  id: number
  name: string
  status: ProjectStatus
  address?: string | null
  capacity_kw?: number | string | null
  install_date?: string | null
  contractor_id: number
  customer_id: number
  created_at: string
  updated_at?: string
  contractor?: { id: number, company_name: string }
  customer?: { id: number, full_name: string }
  battery_systems?: BatterySystem[]
  documents?: DocumentFile[]
  application?: IncentiveApplication
  battery_systems_count?: number
}

export interface AppNotification {
  id: number
  type: string
  payload: Record<string, unknown> | null
  read_at: string | null
  is_read: boolean
  created_at: string
}

export interface AuditLog {
  id: number
  action: string
  subject_type: string | null
  subject_id: number | null
  changes: Record<string, unknown> | null
  user?: { id: number | null, name: string | null }
  created_at: string
}

export type StatusCounts = Record<string, number>

export interface DashboardStats {
  projects: { total: number, by_status: StatusCounts }
  applications: { total: number, by_status: StatusCounts }
  incentives: { reserved_total: number, paid_total: number, scheduled_total: number }
  recent_applications: IncentiveApplication[]
  notifications: { unread_count: number }
}

export interface ContractorOption {
  id: number
  company_name: string
}

export interface CustomerOption {
  id: number
  full_name: string
  account_email: string
}
