# Identity & Access Management (IAM) - Detailed Tasks

### 1. Database Isolation
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (Tenant chip in app shell; tenancy enforced via `tenant` header in `useApi`)
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

### 2. Tenant Onboarding
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/auth/onboard.vue` — workspace name + handle with Zod validation, calls `POST /api/tenants`)
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

### 3. Custom Branding
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
  - ⚠️ `tenants` table is missing `logo_path` / `primary_color` / `secondary_color` columns — migration still needed.
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
  - ⚠️ Endpoint wired but will SQL-error until the columns above exist.
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/iam/branding.vue` — form + live preview; ships behind a warning banner until the migration lands)
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

### 4. Role Management
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/iam/roles/index.vue` — DataTable CRUD + side dialog for permission sync)
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

### 5. Permission Mapping
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/iam/permissions/index.vue` — grouped catalog with search; assignment lives inside Roles)
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

### 6. Inheritance
- [ ] Phase 1: Backend Database & Models — **NOT STARTED**. No `parent_role_id` / inheritance schema in `roles` migration.
- [ ] Phase 2: Backend API & Services — **NOT STARTED**.
- [ ] Phase 3: Frontend UI & Integration — blocked on backend.
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

### 7. MFA/OTP
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation) — `mfa_secret`/`mfa_enabled` columns on users.
- [~] Phase 2: Backend API & Services — endpoints exist as **stubs** (`AuthController::setupMfa` / `verifyMfa` return hardcoded success). Real TOTP integration outstanding.
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/auth/mfa-setup.vue` — two-step setup + 6-digit OTP verification; banner notes the backend stub)
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

### 8. SSO Integration
- [ ] Phase 1: Backend Database & Models — **NOT STARTED**. No SAML/OIDC tables, providers, or config.
- [ ] Phase 2: Backend API & Services — **NOT STARTED**.
- [ ] Phase 3: Frontend UI & Integration — blocked on backend.
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

### 9. Audit Logs
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/iam/audit-logs/index.vue` — DataTable + row detail dialog with old/new value diff)
- [ ] Phase 4: Testing & QA (Pest PHP, Vitest, Security Audit)

---

## Frontend foundations shipped alongside Phase 3

- **Nuxt 3 + TypeScript** project scaffolded under `/frontend`.
- **Tailwind 4** via the Vite plugin + `@import "tailwindcss"`.
- **PrimeVue 4** with a custom `definePreset` based on Aura, indigo brand color, dark mode via `.dark` selector.
- **`useApi` composable** at `composables/useApi.ts` — auto-injects `tenant` header and `Authorization: Bearer <token>`.
- **`useIamApi` composable** at `composables/useIamApi.ts` — typed wrappers for every IAM endpoint.
- **Pinia auth store** at `stores/auth.ts` — persists user/token/tenant in localStorage; hydrated client-side via `plugins/auth-hydrate.client.ts`.
- **Auth middleware** at `middleware/auth.ts` + `middleware/guest.ts` for route guards.
- **Layouts**: `auth.vue` (centered card for login/onboard) and `default.vue` (sidebar + topbar shell with tenant chip + user menu).

## What still needs your hands

Outside this Phase 3 commit:
- Run `cd frontend && npm install` to materialize `node_modules` and the Nuxt `.nuxt/tsconfig.json` referenced by `tsconfig.json`.
- Run `npm run dev` and visit http://localhost:3000 to exercise the flow against the backend at http://localhost:8000.
- ⚠️ Two features (Inheritance, SSO) have no backend yet — frontend stays in the "Not started" column until those land.
