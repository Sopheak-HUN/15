# Project Context: Enterprise ERP (Multi-Tenant)

This is not what you know! Read `rules/*` and `skills/*` before build anything!

## Overview
A high-performance, multi-tenant Enterprise Resource Planning (ERP) system split into two distinct, decoupled projects.

## Project Structure
- **`/backend`**: Laravel 11+ RESTful API.
- **`/frontend`**: Nuxt 3+ Client application.
- **`/skills`**: Shared agent rules and domain-specific standards.

## Tech Stack

### Backend (Laravel Project)
- **Core**: Laravel 11+ (PHP 8.2+)
- **Database**: PostgreSQL (Multi-database via `stancl/tenancy`)
- **Auth**: Laravel Passport (OAuth2, Tenant-scoped)
- **Testing**: Pest PHP (See [QA Testing Rule](./skills/testing/qa-testing.md))

### Frontend (Nuxt Project)
- **Core**: Nuxt 3+ (Vue 3, TypeScript)
- **Styling**: Tailwind CSS 4+ (Latest)
- **UI Components**: PrimeVue (Premium presets)
- **State**: Pinia
- **Testing**: Vitest

## Coding Standards

### Backend (Laravel)
- **Modular Design**: Domain-driven structure (Accounting, Inventory, etc.).
- **Service Layer**: Orchestration logic resides in Services.
- **API First**: Resource-based transformations for all responses.

### Frontend (Nuxt/Vue)
- **Component-Driven**: Atomic design using PrimeVue and Tailwind.
- **Strict Typing**: TypeScript mandatory for all props, states, and composables.
- **Composables**: Abstract business logic into reusable Vue composables.
- **Responsive**: Mobile-first utility-first styling.

## Critical Rules
1. **Separation of Concerns**: The frontend must never contain business logic that belongs in the backend.
2. **Tenant Scoping**: Frontend must include the tenant identifier in all API requests (headers or subdomains).
3. **Atomic Changes**: Use Database Transactions for multi-table updates in the backend.
4. **Audit Everything**: Apply the `Auditable` trait to all key models.
5. **Premium UI**: Adhere to the "Aesthetics Matter" guideline; use modern design tokens.

---

# Specialized Agent Skills

## 1. ERP Structural Implementation
- **Backend (Laravel)**: Modules in `app/Tenants/Modules/`. Use Controllers for routing, Services for logic, and Models with `BelongsToTenant`.
- **Frontend (NuxtJS)**: Modules in `src/modules/`. Use PrimeVue components with `pt` (Pass Through) styling.
- **Service Layer**: All business logic MUST live in Service classes. Use Database Transactions for multi-table updates.

## 2. Backend API & Business Logic
- **Thin Controllers**: Limit to validation and service calling. Always return `JsonResource`.
- **Atomic Services**: Ensure methods are atomic and throw domain-specific exceptions.
- **Model Patterns**: Use UUIDs, Soft Deletes, and `BelongsToTenant` trait.
- **API Security**: Use Laravel Passport and Policies for authorization.

## 3. Authentication & Security
- **OAuth2 Flow**: Use Laravel Passport for SignIn (Access/Refresh tokens) and SignOut.
- **Multi-Tenant Scoping**: Verify that the token's `tenant_id` matches the request context.
- **SSO & Basic Auth**: Support SAML/OIDC for enterprise clients and restricted Basic Auth for internal systems.
- **Token Storage**: Ensure secure storage in the frontend (HttpOnly cookies or secure memory).

## 4. Frontend UI & Features
- **Composition API**: Always use `<script setup lang="ts">`.
- **Reactive Data Fetching**: Use custom `useApi` wrappers to inject `X-Tenant-Handle`.
- **Form Validation**: Use VeeValidate/Vuelidate for complex forms.
- **Branding**: Use CSS variables for dynamic tenant colors and support Dark Mode.

## 4. Multi-Tenant Client Management
- **Handle-Based Routing**: Use the unique company **Handle** (username) for subdomain identification.
- **Database Isolation**: Multi-database strategy. Rely on dynamic connection switching; use the `Landlord` connection only for central data.
- **Scoped Storage**: Use `tenant_path()` for isolated file storage in `storage/tenants/{handle}/`.

## 5. Full-Stack ERP Testing & QA
- **Backend (Pest)**: Prioritize **P0 Tenancy Isolation** tests. Assert that Tenant A cannot access Tenant B's data. (See [Testing Skill](./skills/testing/skill.md))
- **Database Connection Isolation (P0)**: NEVER use active development (`develop`) or production (`production`) database connections when running tests. Tests must run exclusively on a dedicated, isolated testing database (e.g., `DB_DATABASE=erp_system_test`) to prevent data loss or corruption.
- **Frontend (Vitest/Playwright)**: Test component logic and critical user journeys (e.g., Payroll runs).
- **Audit Logs**: Assert that critical business actions create appropriate entries in `audit_logs`.
- **Priority Matrix**: Follow P0 (Security), P1 (Business Logic), P2 (UX/Audit) standards in all test suites.

## 6. Feature-Specific Implementation (Modular)
- **Standardized Documentation**: Every feature must have `rules.md`, `flow.md`, and `testing.md` in its module folder under `skills/features/`.
- **Workflow Integrity**: Follow the step-by-step flows defined in Mermaid diagrams for all business logic implementation.
- **Permission Mapping**: Use the `module.feature.action` pattern defined in `iam.md`.

## 7. Postman & API Documentation
- **Unified Collection**: Maintain all endpoints in `docs/postman/erp_collection.json`.
- **Automation**: Include pre-request scripts for token/ID capture and realistic response examples.
- **Headers**: Enforce the mandatory `tenant: {{tenant_id}}` header for all requests.
- **Continuous Documentation**: Whenever a new feature is created or an existing feature is updated, the associated Postman collection and related API documentation MUST be updated simultaneously.

## 8. Docker Infrastructure & Containerization
- **Multi-Stage Builds**: Use Builder and Runner stages to keep production images small (Alpine-based).
- **Service Orchestration**: Use `depends_on` with `service_healthy` to ensure DB readiness.
- **Standard Template**: Follow the standardized `docker-compose.yml` boilerplate for initial setup.

## 9. Version Control & Project Updates
- **Versioning**: Follow `{MAJOR}.{MINOR}.{PATCH}`. Update `package.json`, `README.md`, and `SECURITY.md` simultaneously.
- **Consistency**: Never bump a version in isolation. Ensure the API reflects the current state.

## 10. Scalable WebSockets (Real-time)
- **Infrastructure**: Use Laravel Reverb or Redis Pub/Sub for multi-instance scaling.
- **Tenant Scoping**: Prefix all channels with the tenant `handle`.
- **Security**: Authenticate private channels via Laravel Passport.
- **Optimization**: Queue all broadcast events; keep payloads minimal.

## 11. Audit & Compliance
- **Audit Logging**: Use the `Auditable` trait on all models.
- **Traceability**: Record old/new values, actor handle, and timestamp for all critical business actions.
- **Compliance**: Verify that Audit logs are generated for every P0 and P1 priority operation.

## 12. Skills Management CLI
- **Usage**: Fetch and synchronize skills using `npx skills@latest add <repo>`.
- **Repository**: Standard skills are maintained in `pphatdev/erp-prompt`.
- **Automation**: Use this tool to bootstrap new ERP modules with the latest agent rules and standards.

## 13. Task & Infrastructure Tracking
- **Task Context Storage**: Upon the first analysis of any task or feature, the agent **MUST** inspect the local `.task/` directory and create/maintain a task-specific folder with exact context and status trackers: `.task/{feature}/{task.md, context.md}`.
- **Master Checklist Sync**: Synchronize and link all new feature scopes or significant refactors into the master progress registry at `.task/task.md` using appropriate checkbox markers (`[ ]` or `[x]`). This maintains perfect project traceability.
- **Feature Status Synchronization**: Always inspect the codebase to compare implemented features against the checklists in the `.task/` directory. If any features have been completed or changed in the code, immediately update the corresponding checkboxes and status descriptions in the `.task/` tracker files.


## 14. Data Safety & Security
- **Agent Safety**: Never load or share production customer data, secrets, or active environments in local setups or prompt contexts. Use Faker-generated mock data only.
- **Database Preservation**: The agent must avoid automatic deletion of database schemas, tables, records, or files. Destructive tasks (e.g. running `migrate:fresh` or `db:wipe`) must seek user verification and confirmation 2 to 3 times before execution.
- **User Safety**: Enforce field-level encryption for sensitive PII (salaries, SSNs), automatically sanitize passwords/keys in logging channels, and strictly switch isolated tenant connections on every request.

## 15. File Uploading & Storage Management
- **Security Validation**: Always validate file types server-side using fileinfo magic bytes, block executable extensions, and sanitize filenames.
- **Tenancy Scoping (P0)**: Store all uploaded assets strictly within the tenant-isolated directory via `tenant_path()`. Never expose direct paths to private assets; use cryptographically signed, short-lived URLs.
- **Chunked Uploads**: Implement chunked file transfers for uploads larger than 10MB to maintain low memory footprints and avoid request timeouts.
- **Traceability**: Record file metadata (size, hash, uploader, MIME type) in the attachments/media tables and ensure files are deleted or archived per data retention policies.



