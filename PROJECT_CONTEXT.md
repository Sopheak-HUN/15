# Enterprise ERP Project Context Skills

## Project Overview
This is a high-performance, multi-tenant Enterprise Resource Planning (ERP) system designed for scalability and data isolation. It is split into a Laravel-based RESTful API and a Nuxt 3-based client application.

## Core Architecture
- **Multi-Tenancy**: Multi-database isolation using `stancl/tenancy`. Tenant data is physically separated.
- **Backend**: Laravel 11+ (PHP 8.2+), PostgreSQL, Laravel Passport (OAuth2).
- **Frontend**: Nuxt 3+ (Vue 3, TypeScript), Tailwind CSS 4+, PrimeVue (Premium presets).
- **Agent System**: Standardized skills and rules located in `/skills` and `/rules`.

## Project Structure
- `/backend`: Laravel Multi-tenant API source.
- `/frontend`: NuxtJS Client application source.
- `/skills`: Domain-specific agent skills (e.g., IAM, FMS, HRM).
- `/rules`: Global project rules and standards.
- `/tools`: Internal CLI tools (e.g., `skills-cli`).

## Critical Coding Standards
### 1. Multi-Tenant Isolation (P0)
- **Backend**: Models must use the `BelongsToTenant` trait. All migrations must be placed in `database/migrations/tenant`.
- **Frontend**: API requests must include the `tenant` header carrying the tenant handle. Use the `useApi` wrapper. Resolution is performed by Stancl's `InitializeTenancyByRequestData` middleware, configured in `TenancyServiceProvider`.

### 2. Business Logic (P1)
- **Service Layer**: Controllers must be thin; all business logic resides in `Services`.
- **Atomic Operations**: Use Database Transactions for any operation affecting multiple tables.
- **Audit Logging**: All critical actions must use the `Auditable` trait for traceability.

### 3. UI/UX (P2)
- **Premium Design**: Use PrimeVue components with customized design tokens. Avoid generic styling.
- **Responsive**: Mobile-first design is mandatory.

## Active Modules & Skills
Every module follows a standardized structure with a `SKILL.md` (metadata), `rules.md` (logic), `flow.md` (workflow), and `testing.md` (QA).

| Module | Location | Purpose |
| :--- | :--- | :--- |
| **IAM** | `/skills/iam` | Identity, RBAC, and Tenant Lifecycle. |
| **FMS** | `/skills/fms` | General Ledger, AP/AR, and Tax. |
| **HRM** | `/skills/hrm` | Workforce, Payroll, and Leave. |
| **Sales** | `/skills/sales` | CRM and Order-to-Cash (O2C). |
| **Inventory** | `/skills/inventory` | WMS, P2P, and Logistics. |
| **Fleet** | `/skills/fleet` | Vehicle tracking and Maintenance. |
| **Assets** | `/skills/assets` | Fixed Asset Management & Depreciation. |
| **Projects** | `/skills/projects` | Project Planning and Task Tracking. |
| **Reporting** | `/skills/reporting` | Dashboards and Analytics. |
| **Documents** | `/skills/documents` | Advanced Document Workflows. |
| **eApprovals** | `/skills/eapprovals` | Centralized Approval Engine. |
| **eDocuments** | `/skills/edocuments` | Policy Repository & Explorer. |

## Testing & QA
- **Backend**: Use Pest PHP. Prioritize Tenancy Isolation tests (P0).
- **Frontend**: Use Vitest for components and Playwright for E2E.

## Documentation References
- [Main Rules](./rules/structure/skill.md)
- [Agent Standards](./rules/agent/SKILL.md)
- [Security Policy](./SECURITY.md)
