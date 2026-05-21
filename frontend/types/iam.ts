export interface User {
  id: string
  name: string
  handle: string
  email: string
  role_id: string | null
  mfa_enabled: boolean
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Role {
  id: string
  name: string
  description: string | null
  parent_role_id?: string | null
  parent?: Pick<Role, 'id' | 'name'> | null
  permissions?: Permission[]
  created_at: string
  updated_at: string
}

export interface Permission {
  id: string
  name: string
  description: string | null
  created_at: string
  updated_at: string
}

export interface AuditLog {
  id: string
  tenant_id: string
  user_id: string | null
  action: string
  auditable_type: string
  auditable_id: string
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  created_at: string
  updated_at: string
}

export interface Paginated<T> {
  current_page: number
  data: T[]
  first_page_url: string
  from: number | null
  last_page: number
  last_page_url: string
  next_page_url: string | null
  path: string
  per_page: number
  prev_page_url: string | null
  to: number | null
  total: number
}

export interface ApiEnvelope<T> {
  success: boolean
  data: T
  message?: string
}

export interface ApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export interface Tenant {
  id: string
  name: string
  handle: string
  status: string
  logo_path?: string | null
  primary_color?: string | null
  secondary_color?: string | null
}

export interface SsoProvider {
  id: string
  name: string
  protocol: 'oidc' | 'saml'
  issuer?: string | null
  client_id?: string | null
  discovery_url?: string | null
  redirect_uri?: string | null
  scopes?: string[] | null
  metadata?: Record<string, unknown> | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface SsoProviderInput {
  name: string
  protocol: 'oidc' | 'saml'
  issuer?: string | null
  client_id?: string | null
  client_secret?: string | null
  discovery_url?: string | null
  redirect_uri?: string | null
  scopes?: string[] | null
  metadata?: Record<string, unknown> | null
  is_active?: boolean
}
