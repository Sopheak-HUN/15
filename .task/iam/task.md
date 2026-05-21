# Identity & Access Management (IAM) - Detailed Tasks

### 1. Database Isolation
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (Tenant chip in app shell; tenancy enforced via `tenant` header in `useApi`)
- [x] Phase 4: Testing & QA — `tests/Feature/IAM/TenancyIsolationTest.php` covers cross-tenant role + audit-log leakage. Skips when no central connection is configured.

### 2. Tenant Onboarding
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/auth/onboard.vue` — workspace name + handle with Zod validation, calls `POST /api/tenants`)
- [x] Phase 4: Testing & QA — exercised by the tenancy isolation suite, which provisions+tears down two tenants per test.

### 3. Custom Branding
- [x] Phase 1: Backend Database & Models — `2026_05_21_100000_add_branding_columns_to_tenants_table.php` adds `logo_path`, `primary_color`, `secondary_color`; `Tenant::getCustomColumns()` updated.
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/iam/branding.vue` — form + live preview; warning banner removed now that the schema lands)
- [ ] Phase 4: Testing & QA — manual smoke only; no automated tests yet.

### 4. Role Management
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/iam/roles/index.vue` — DataTable CRUD + side dialog for permission sync; parent-role Select + inherited-permission view)
- [x] Phase 4: Testing & QA — `tests/Unit/IAM/RoleInheritanceTest.php` covers merge logic, cycle short-circuit, and `isAncestorOf`. End-to-end CRUD covered by `TenancyIsolationTest`.

### 5. Permission Mapping
- [x] Phase 1: Backend Database & Models (Laravel Migrations, Multi-tenant Isolation)
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/iam/permissions/index.vue` — grouped catalog with search; assignment lives inside Roles)
- [ ] Phase 4: Testing & QA — covered indirectly by role inheritance tests; no dedicated suite yet.

### 6. Inheritance
- [x] Phase 1: Backend Database & Models — `2026_05_21_100100_add_parent_role_id_to_roles_table.php` adds nullable self-FK with `nullOnDelete`.
- [x] Phase 2: Backend API & Services — `Role::effectivePermissions()` walks the parent chain (cycle-safe). `RoleService::guardParent()` rejects self-reference and descendant-cycles. `RoleController::show` exposes the effective set.
- [x] Phase 3: Frontend UI & Integration — Roles dialog has an "Inherits from" Select; permission dialog shows inherited badges and counts.
- [x] Phase 4: Testing & QA — `RoleInheritanceTest` (pure unit, no DB).

### 7. MFA/OTP
- [x] Phase 1: Backend Database & Models — `mfa_secret`/`mfa_enabled` columns on users.
- [x] Phase 2: Backend API & Services — `TotpService` implements RFC 6238 in-house (SHA-1, 6 digits, 30s period, ±1 window). `AuthController::setupMfa` returns secret + `otpauth://` URI; `verifyMfa` enables on first successful code.
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/auth/mfa-setup.vue` — renders the QR via `api.qrserver.com`, supports manual secret entry, full 6-digit OTP verification)
- [x] Phase 4: Testing & QA — `tests/Unit/IAM/TotpServiceTest.php` covers secret format, URI shape, in-window verification, skew tolerance, malformed-code rejection, cross-secret rejection.

### 8. SSO Integration
- [x] Phase 1: Backend Database & Models — `2026_05_21_100200_create_sso_providers_table.php` (tenant-scoped) with encrypted `client_secret`; adds `sso_provider_id` + `sso_subject` to `users`.
- [x] Phase 2: Backend API & Services — `OidcService` implements the OIDC Authorization Code flow (discovery cache, token exchange, JIT user provisioning). `SsoController` exposes CRUD + `/redirect` + `/callback`. SAML is reserved (rejected with 422) pending a dedicated service.
- [x] Phase 3: Frontend UI & Integration — `frontend/pages/iam/sso/index.vue` admin CRUD; `frontend/pages/auth/sso-callback.vue` handles the IdP redirect and stores the session.
- [ ] Phase 4: Testing & QA — needs Http::fake() coverage for the OIDC flow + signed-JWT validation, which is a follow-up alongside JWKS signature verification.

### 9. Audit Logs
- [x] Phase 1: Backend Database & Models — also fixed `AuditLog` missing `HasUuids` (was causing NOT-NULL `id` insert failures).
- [x] Phase 2: Backend API & Services (Controllers, Resources, Domain Logic)
- [x] Phase 3: Frontend UI & Integration (`frontend/pages/iam/audit-logs/index.vue` — DataTable + row detail dialog with old/new value diff)
- [x] Phase 4: Testing & QA — tenant scoping verified by `TenancyIsolationTest::it scopes audit log queries to the active tenant`.

---

## Known follow-ups (not blocking IAM completion)

- **OIDC signature verification** — `OidcService::decodeIdTokenClaims()` currently base64-decodes the JWT payload without verifying the IdP signature against JWKS. Acceptable for scaffolding; must be hardened before any production rollout.
- **SAML** — schema and routes are in place but the controller rejects SAML with 422 until a proper service lands.
- **Login-time MFA challenge** — the verify endpoint enables MFA after first successful code, but `AuthController::login` doesn't yet require a second factor when `mfa_enabled = true`. Tracked as a follow-up so we don't lock out existing users mid-rollout.
- **Branding / Permission dedicated tests** — covered indirectly today; add Pest feature tests when the test-DB pipeline is stable.

## Frontend foundations shipped alongside Phase 3

- **Nuxt 3 + TypeScript** project scaffolded under `/frontend`.
- **Tailwind 4** via the Vite plugin + `@import "tailwindcss"`.
- **PrimeVue 4** with a custom `definePreset` based on Aura, indigo brand color, dark mode via `.dark` selector.
- **`useApi` composable** at `composables/useApi.ts` — auto-injects `tenant` header and `Authorization: Bearer <token>`.
- **`useIamApi` composable** at `composables/useIamApi.ts` — typed wrappers for every IAM endpoint (now includes role inheritance, SSO providers, OIDC callback).
- **Pinia auth store** at `stores/auth.ts` — persists user/token/tenant in localStorage; hydrated client-side via `plugins/auth-hydrate.client.ts`.
- **Auth middleware** at `middleware/auth.ts` + `middleware/guest.ts` for route guards.
- **Layouts**: `auth.vue` (centered card for login/onboard/sso-callback) and `default.vue` (sidebar + topbar shell — SSO link added).

## What still needs your hands

- Run `cd backend && php artisan migrate` to apply the three new migrations (central branding + tenant `parent_role_id` + tenant `sso_providers`).
- For the tenancy-isolation suite, point `DB_DATABASE=erp_system_test` and re-run `php artisan test --filter=TenancyIsolation`. The suite skips itself when the central connection isn't reachable, so unconfigured CI won't fail.
- For SSO, register a redirect URI of the form `https://app.example.com/auth/sso-callback?tenant=<handle>` at your IdP and store the discovery URL + client credentials via the new admin page.
