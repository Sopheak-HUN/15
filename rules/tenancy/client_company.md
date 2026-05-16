# Tenancy Rules: Client Company Management

## Overview
In this ERP, a **Tenant** represents a **Client Company**. Each company is completely isolated from others, ensuring data privacy, security, and independent configuration.

## 1. Tenant Identification & Routing
- **Handle-Based Routing**: Each client company accesses the ERP via a dedicated subdomain derived from their unique **Handle** or username (e.g., `my-company.erp.com`).
- **Identification**: The system identifies the tenant by matching the request subdomain against the `handle` column in the central `tenants` table.
- **X-Tenant-Handle Header**: For cross-origin API requests where subdomains aren't automatically captured, the `X-Tenant-Handle` header must be used.
- **Custom Domains**: Support for custom domains mapping (e.g., `erp.my-company.com` -> `my-company.erp.com`) must be handled via the Landlord mapping table.

## 2. Data Isolation
- **Database Strategy**: Use **Multi-Database Isolation**. Each Client Company has its own dedicated PostgreSQL database.
- **Connection Management**: The system must dynamically switch the database connection based on the active tenant identified during the request lifecycle.
- **Shared Tables**: Only system-level tables (Tenants, Subscriptions, Global Settings) live in the `landlord` (central) database.
- **Encryption**: Sensitive company data (Financial records, Employee PI) must be encrypted using a tenant-specific key managed via a Key Management System (KMS).

## 3. Client Onboarding & Lifecycle
- **Self-Service Onboarding**: New client companies can register, select a plan, and have their database automatically provisioned.
- **Migration Orchestration**: When running updates, migrations must be executed across all tenant databases sequentially or in parallel using `tenants:migrate`.
- **Suspension**: If a subscription expires, the tenant must be marked as `inactive`, blocking all API access while preserving data for a grace period.

## 4. Branding & Customization
- **White Labeling**: Clients can upload their logo, define primary/secondary colors, and set their corporate font.
- **Design Tokens**: Frontend design tokens must reactively update based on the tenant's configuration fetched from the `useTenant` composable.
- **Module Toggling**: Features (e.g., HRM, Inventory) are enabled or disabled based on the client's subscription plan.

## 5. Security & Compliance
- **Isolation Verification**: Automated tests must verify that no data from Tenant A can be accessed by Tenant B, even with manual URL manipulation.
- **Audit Trails**: Every administrative action within a client company must be logged with the user's ID, timestamp, and IP address.
- **Data Export/Deletion**: Clients must have the ability to export their entire dataset or request a "right to be forgotten" (GDPR compliance) via a secure admin tool.

## 6. Technical Implementation (Laravel)
- **Trait**: All tenant-aware models must use the `BelongsToTenant` trait.
- **Middleware**: The `tenancy` middleware must be the first in the stack for all routes under the `Tenants/` namespace.
- **Storage**: Tenant files (contracts, images) must be stored in isolated directories: `storage/tenants/{tenant_id}/*`.
