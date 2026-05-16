# Skill: Full-Stack ERP Testing & QA

## Context
Use this skill when implementing automated tests for new features, verifying bug fixes, or ensuring system stability across the ERP ecosystem. This standard covers both Backend (Laravel/Pest) and Frontend (Nuxt/Vitest) testing to guarantee multi-tenant security and business logic integrity.

## Backend Testing Guidelines (Laravel & Pest)

### 1. Test Organization
- **Tool**: Use **Pest PHP** for all backend testing.
- **Feature Tests**: Focus on API endpoints, authentication, and tenancy isolation (`tests/Feature`).
- **Unit Tests**: Focus on isolated business logic in Services and Helpers (`tests/Unit`).

### 2. Multi-Tenant Isolation (P0 Priority)
- **Isolation Checks**: Every test involving tenant data MUST verify that records are inaccessible from other tenant contexts.
- **Example**: Assert that a request from Tenant B to a resource owned by Tenant A returns a `404` or `403`.
- **Environment**: Use the `RefreshDatabase` trait and the `initializeTenancy()` helper for clean, tenant-scoped test runs.

### 3. API & Data Verification
- **Status Codes**: Test for `200/201` (Success), `422` (Validation), and `403` (Unauthorized).
- **JSON Structure**: Use `assertJsonStructure` to verify API contracts align with frontend expectations.
- **Factories**: Always use Model Factories for data generation to ensure schema consistency.

## Frontend Testing Guidelines (Nuxt & Vitest)

### 1. Unit & Component Testing
- **Tool**: Use **Vitest** and **Vue Test Utils**.
- **Coverage**: Focus on complex component logic, custom composables (e.g., `useInvoicing`), and utility functions.
- **Mocking**: Mock API responses using `msw` (Mock Service Worker) or simple vitest spies to avoid dependency on a running backend.

### 2. E2E & Integration Testing
- **Tool**: Use **Playwright** for critical user journeys (e.g., "Complete Payroll Run", "Inventory Adjustment").
- **Tenant Context**: Ensure E2E tests are run against a specific tenant subdomain to verify the handle-based routing.

## Best Practices
- **Atomic Tests**: Keep tests focused on a single assertion or behavior.
- **Audit Logging**: For critical operations (Financial/HR), assert that appropriate entries are created in the `audit_logs` table.
- **Clean Slate**: Never let state leak between tests. Use appropriate setup/teardown methods.
- **CI Integration**: Ensure all tests pass in the local environment before pushing to the repository.

## Troubleshooting
- **Tenant DB Issues**: If tests fail due to missing tables, ensure `php artisan tenants:migrate --database=testing` has been executed.
- **Hydration Errors in Tests**: When testing components, ensure the environment is correctly set to `jsdom` or `happy-dom`.
- **Async Failures**: Ensure all promises and API mocks are properly awaited in both Pest and Vitest.
- **Auth Persistence**: Verify that `actingAs()` is called within the correct tenant scope for backend feature tests.
