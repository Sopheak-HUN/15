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

### Core Authentication Flow
1. **Credentials Verification**: Authenticate via Passport.
2. **MFA Challenge**: Require OTP for admin/finance roles.
3. **Tenant Context**: Set global `tenant_id` scope for all subsequent queries.
4. **Authorization**: Check `module.feature.action` before execution.
5. **Audit**: Log activity details (actor, timestamp, payload).

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\IAM`
- **Service Layer**: Use `TenantService.php`, `UserService.php`, `RoleService.php`.
- **Security**: Critical permission changes MUST require OTP verification.
- **Logging**: All changes to roles/permissions MUST be recorded in `audit_logs`.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/iam/`
- **Directives**: Use `v-can` to restrict access to the Admin Dashboard.
- **Security**: Sensitive tokens must be stored in HttpOnly cookies.
