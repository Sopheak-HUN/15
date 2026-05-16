# Skill: Identity & Access Management (IAM) Implementation

## Context
Use this skill when managing tenant lifecycle, user authentication, role-based access control (RBAC), or auditing security events. This ensures the ERP remains secure, compliant, and properly isolated between tenants.

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
- **Tenant Mismatch**: If a user logs into the wrong tenant, verify the `X-Tenant-Handle` header matches the user's assigned tenant.
