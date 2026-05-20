# Authentication & Authorization Standards

## 1. Overview
This project uses **Laravel Passport** to provide a secure, OAuth2-compliant authentication system. Every authentication action must be tenant-aware and scoped correctly.

## 2. Authentication Flows

### Sign In (Authentication)
- **Grant Type**: Use the `Password Grant` or `Authorization Code Grant` with PKCE.
- **Payload**: Requires `email`, `password`, and the tenant `handle`.
- **Response**: Returns a JSON object containing:
  - `access_token`: Short-lived (e.g., 1 hour).
  - `refresh_token`: Long-lived (e.g., 30 days).
  - `expires_in`: Seconds until expiration.
  - `user`: Basic user profile and permissions.

### Refresh Token
- **Flow**: Clients must use the `refresh_token` to obtain a new `access_token` without re-authenticating.
- **Endpoint**: `POST /oauth/token` with `grant_type: refresh_token`.

### Sign Out
- **Revocation**: The `access_token` must be revoked in the database.
- **Global Sign Out**: Option to revoke all active tokens for the user across all devices.

### Sign Up (Onboarding)
- **Tenant Creation**: Sign up usually involves creating a new **Tenant** (Client Company) and the first **Admin User**.
- **OTP Verification**: Mandatory **One-Time Password (OTP)** verification via Email or SMS must be completed before the tenant account or admin user is activated.
- **Validation**: Strict validation for email uniqueness and secure password complexity.

## 3. Advanced Authentication

### Basic Auth
- **Usage**: Restricted to **Internal System-to-System** communication only (e.g., between internal microservices or a secure cron runner).
- **Security**: Must be wrapped in HTTPS and used with a rotating `Secret Key`.

### Single Sign-On (SSO)
- **Protocols**: Support for **SAML 2.0** and **OpenID Connect (OIDC)**.
- **Mapping**: External identities (e.g., Azure AD, Okta) must be mapped to the internal `User` and `Role` system based on the tenant's configuration.
- **Just-In-Time (JIT) Provisioning**: Automatically create users on their first successful SSO login if configured.

## 4. Multi-Tenant Scoping
- **Token Scope**: Every token issued is scoped to a specific `tenant_id`. 
- **Validation**: The backend must verify that the `tenant_id` in the token matches the `handle` (subdomain) of the current request.
- **Cross-Tenant Access**: Users with multi-tenant access (e.g., Global Admins) must switch contexts, which issues a new token for the target tenant.

## 6. Authorization & Role Management

### Role-Based Access Control (RBAC)
- **Standard Roles**: Every tenant starts with a set of default roles (e.g., `Super Admin`, `HR Manager`, `Finance User`).
- **Permissions**: Roles are a collection of fine-grained permissions. Permissions must follow a dot-notation namespace (e.g., `hr.employee.view`, `accounting.invoice.delete`).

### Dynamic Role Creation
- **Customization**: Client companies must be able to create custom roles and assign specific permissions to them.
- **Inheritance**: (Optional) Roles can inherit permissions from other roles to simplify management.

### Technical Implementation (Laravel)
- **Policies**: Use Laravel **Policies** for model-level authorization (e.g., `InvoicePolicy`).
- **Gates**: Use **Gates** for action-level authorization that isn't tied to a specific model.
- **Middleware**: Use the `can:` middleware in routes to restrict access based on permissions.
  - `Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('can:accounting.invoice.create');`

### Frontend Authorization (Nuxt)
- **Directives**: Use a custom `v-can` directive or a `usePermissions` composable to hide/show UI elements.
- **Route Guards**: Implement a global router guard that checks the `meta.permission` field against the user's cached permission list.

### API Scopes (Passport)
- **Granular Tokens**: Use Passport **Scopes** to limit the capabilities of tokens issued to third-party integrations or specific client apps.
- **Validation**: Scopes should be checked in addition to user permissions for high-security endpoints.

## 7. Audit & Compliance
- **Permission Changes**: Every change to a role's permissions or a user's role assignment must be logged in the `audit_logs`.
- **Review**: Provide an interface for HR/Security admins to review all active permissions for any specific user.
