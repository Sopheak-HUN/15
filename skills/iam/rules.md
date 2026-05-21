# IAM Module Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `iam`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `tenants` | `iam.tenants.read` | `iam.tenants.write` | `iam.tenants.delete` | `iam.tenants.export` |
| `users` | `iam.users.read` | `iam.users.write` | `iam.users.delete` | `iam.users.export` |
| `roles` | `iam.roles.read` | `iam.roles.write` | `iam.roles.delete` | `iam.roles.export` |
| `permissions` | `iam.permissions.read` | `iam.permissions.write` | - | `iam.permissions.export` |
| `audit` | `iam.audit.read` | - | - | `iam.audit.export` |

## 2. Implementation Standards

### Tenant Header (P0)
Every tenant-scoped request MUST include the `tenant` HTTP header carrying the tenant handle (e.g. `tenant: acme`). Stancl's `InitializeTenancyByRequestData` middleware reads this header (or the `?tenant=` query param as a fallback) and swaps the active DB connection before the controller runs. Subdomain-based identification is not used. Full reference: [`docs/api-authentication.md`](../../docs/api-authentication.md).

### Core Authentication Flow
1. **Tenant Resolution**: Stancl middleware reads the `tenant` header and initializes the tenant DB connection.
2. **Credentials Verification**: Authenticate via Passport (`Auth::attempt`) — the lookup hits the now-active tenant `users` table.
3. **MFA Challenge**: Require OTP for admin/finance roles.
4. **Token Issuance**: `$user->createToken()` uses the per-tenant `oauth_clients` row + the shared signing keys at `storage/oauth-*.key`.
5. **Authorization**: Check `module.feature.action` before execution.
6. **Audit**: Log activity details (actor, timestamp, payload).

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\IAM`
- **Service Layer**: Use `TenantService.php`, `UserService.php`, `RoleService.php`.
- **Security**: Critical permission changes MUST require OTP verification.
- **Logging**: All changes to roles/permissions MUST be recorded in `audit_logs`.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/iam/`
- **Directives**: Use `v-can` to restrict access to the Admin Dashboard.
- **Security**: Sensitive tokens must be stored in HttpOnly cookies.
- **Tenant Header**: The `useApi` wrapper MUST inject the `tenant` header on every outgoing request (read from the active tenant handle in the auth store or route). A request without it will not resolve a tenant context and will fail.
