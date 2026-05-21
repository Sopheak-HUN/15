---
name: identity-and-access-management
description: Manage tenant lifecycle, user authentication, role-based access control (RBAC), and security auditing.
---
# Identity & Access Management (IAM)

Use this skill when managing tenant lifecycle, user authentication, role-based access control (RBAC), or auditing security events. This ensures the ERP remains secure, compliant, and properly isolated between tenants.

## Workflows
1. **User Onboarding**: Register new users, assign tenant handles, and seed initial RBAC roles.
2. **Authentication Flow**: Manage secure sign-in via Laravel Passport, including MFA and token rotation.
3. **Permission Validation**: Verify user access levels using the `module.feature.action` pattern before executing business logic.

## Guidelines

### 1. Multi-Tenant Isolation
- **Tenant Scope**: Every database query must be scoped via the `tenant_id`. Verify that the `BelongsToTenant` trait is active.
- **Onboarding**: New tenants must trigger a database migration and seed default system roles.

### 2. Authentication (Laravel Passport)
- **Scopes**: Use Passport scopes to manage coarse-grained access (e.g., `admin`, `api-access`).
- **MFA**: Enforce Multi-Factor Authentication for users with `Super Admin` or `Finance Manager` roles.

### 3. Role-Based Access Control (RBAC)
- **Permissions**: Follow the `module.feature.action` pattern (e.g., `iam.users.write`).
- **Dynamic Checks**: Use the `v-can` directive in the frontend and `Authorize` policies in the backend.

### 4. Auditing & Compliance
- **Activity Logs**: All changes to roles, permissions, or tenant settings MUST be logged in the `audit_logs` table.
- **Traceability**: Include the `actor_id`, `ip_address`, and `user_agent` in all security logs.

## Best Practices
- **Least Privilege**: Grant the minimum permissions required for a user's role.
- **OTP for Critical Actions**: Require a one-time password for sensitive actions like deleting a tenant or modifying system roles.
- **Soft Deletes**: Never hard-delete users or roles; use soft deletes to maintain audit history.

## Troubleshooting
- **Unauthorized (403)**: Check if the user's role has the specific permission key in the `role_permissions` table.
- **Token Expired**: Ensure the frontend handles refresh tokens correctly using the Passport OAuth2 flow.
- **Tenant Mismatch**: If a user logs into the wrong tenant, verify the `tenant` request header matches the user's assigned tenant handle. Tenant resolution is wired via Stancl's `InitializeTenancyByRequestData` middleware (see [TenancyServiceProvider.php](../../backend/app/Providers/TenancyServiceProvider.php)).
- **`Invalid key supplied` on login**: Passport keys live at `storage/oauth-*.key`. On a fresh container, file perms must be 0600 (the entrypoint handles this on boot). If running outside Docker, set perms manually.
- **`Personal access client not found`**: Each tenant DB needs its own oauth_clients row. Run `php artisan tenants:seed --tenants=<handle>` after onboarding.
