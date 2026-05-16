# Skill: Multi-Tenant Client Management

## Context
Use this skill when developing features that require tenant isolation, managing client company onboarding, or implementing cross-tenant logic. This ensures that every client company (tenant) is strictly isolated and their data is handled securely via the Handle-Based Routing system.

## Guidelines

### 1. Tenant Identification & Routing
- **Subdomain Context**: Always assume the primary method of identification is the request subdomain (the `handle`).
- **Middleware Usage**: Ensure the `InitializeTenancyByDomain` middleware is active for all tenant-specific routes.
- **Header Injection**: When performing cross-origin requests, manually inject the `X-Tenant-Handle` header.

### 2. Working with Isolated Databases
- **Dynamic Connections**: Never hardcode database connections. Rely on the tenancy package to switch the active connection automatically based on the `handle`.
- **Landlord Access**: When data from the central database is needed (e.g., subscription checks), use the `Landlord` connection explicitly.
- **Migration Scope**: Use the `--tenants` flag when running migrations to ensure schema changes apply to all client company databases.

### 3. Tenant-Aware Features
- **Scoped Storage**: Use the `tenant_path()` helper to store files. This ensures assets are saved in `storage/tenants/{handle}/`.
- **Global Scopes**: Always apply the `BelongsToTenant` trait to models that should be scoped to a single company.
- **Cache Isolation**: Use tenant-specific cache prefixes to prevent data leakage between companies.

### 4. Branding & Personalization
- **useTenant Composable**: In the frontend, use `useTenant()` to fetch the active company's logo, colors, and enabled modules.
- **Design Tokens**: Bind CSS variables to the values returned by the tenant configuration to support white-labeling.

## Best Practices
- **Isolation First**: Always write automated tests that verify "Tenant A" cannot access "Tenant B" data.
- **Idempotent Setup**: Ensure the client onboarding process is idempotent; if provisioning fails halfway, it should be able to resume without creating duplicate databases.
- **Handle Validation**: Enforce strict regex validation for company handles (e.g., `^[a-z0-9-]+$`) to prevent URL-unfriendly subdomains.

## Troubleshooting
- **Missing Database**: If a "Database not found" error occurs, verify that the tenant's `handle` matches the database name or the mapping in the central `tenants` table.
- **Shared Data Leakage**: If data from one company appears in another, check if the `BelongsToTenant` trait is missing or if the model is using a shared cache key.
- **Routing Loop**: If subdomains are redirecting incorrectly, check the `SESSION_DOMAIN` configuration in the `.env` file to ensure cookies are shared across subdomains.
