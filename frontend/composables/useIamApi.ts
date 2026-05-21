import type {
  ApiEnvelope,
  AuditLog,
  Paginated,
  Permission,
  Role,
  SsoProvider,
  SsoProviderInput,
  Tenant,
  User,
} from '~/types/iam'

/**
 * Typed wrappers for the IAM endpoints exposed by the Laravel backend.
 * Matches routes/api.php (central) + routes/tenant.php (per-tenant).
 */
export function useIamApi() {
  const api = useApi()

  return {
    // Central
    onboardTenant: (body: { name: string; handle: string }) =>
      api.post<{ success: true; tenant: Tenant }>('/api/tenants', body, { central: true }),

    // Auth
    login: (body: { email: string; password: string }) =>
      api.post<ApiEnvelope<{ user: User; token: string }>>('/api/auth/login', body),
    logout: () =>
      api.post<{ success: boolean; message: string }>('/api/auth/logout'),
    setupMfa: () =>
      api.post<{ success: boolean; message: string }>('/api/auth/mfa/setup'),
    verifyMfa: (body: { code: string }) =>
      api.post<{ success: boolean; message: string }>('/api/auth/mfa/verify', body),

    // Roles
    listRoles: () => api.get<{ data: Role[] }>('/api/iam/roles'),
    showRole: (id: string) =>
      api.get<{ data: { role: Role; effective_permissions: Permission[] } }>(`/api/iam/roles/${id}`),
    createRole: (body: { name: string; description?: string; parent_role_id?: string | null }) =>
      api.post<{ success: boolean; data: Role }>('/api/iam/roles', body),
    updateRole: (id: string, body: { name: string; description?: string; parent_role_id?: string | null }) =>
      api.put<{ success: boolean; data: Role }>(`/api/iam/roles/${id}`, body),
    deleteRole: (id: string) =>
      api.del<{ success: boolean }>(`/api/iam/roles/${id}`),
    syncRolePermissions: (id: string, permissionIds: string[]) =>
      api.post<{ success: boolean; data: Role }>(`/api/iam/roles/${id}/permissions`, { permission_ids: permissionIds }),

    // Permissions
    listPermissions: () => api.get<{ data: Permission[] }>('/api/iam/permissions'),

    // Audit logs
    listAuditLogs: () => api.get<{ data: Paginated<AuditLog> }>('/api/iam/audit-logs'),

    // Branding
    updateBranding: (body: { logo_path?: string; primary_color?: string; secondary_color?: string }) =>
      api.put<{ success: boolean; data: Tenant }>('/api/iam/branding', body),

    // SSO
    listSsoProviders: () =>
      api.get<{ data: SsoProvider[] }>('/api/auth/sso/providers'),
    createSsoProvider: (body: SsoProviderInput) =>
      api.post<{ success: boolean; data: SsoProvider }>('/api/iam/sso-providers', body),
    updateSsoProvider: (id: string, body: Partial<SsoProviderInput>) =>
      api.put<{ success: boolean; data: SsoProvider }>(`/api/iam/sso-providers/${id}`, body),
    deleteSsoProvider: (id: string) =>
      api.del<{ success: boolean }>(`/api/iam/sso-providers/${id}`),
    ssoRedirect: (id: string) =>
      api.get<{ success: boolean; data: { authorization_url: string } }>(`/api/auth/sso/${id}/redirect`),
    ssoCallback: (body: { code: string; state: string }) =>
      api.post<ApiEnvelope<{ user: User; token: string }>>('/api/auth/sso/callback', body),
  }
}
