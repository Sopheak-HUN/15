# Skill: Backend QA Feature Testing

## Context
Use this rule when implementing or verifying backend features in the Laravel Enterprise ERP ecosystem. This standard ensures that all business logic is covered by automated tests, follows strict multi-tenant isolation protocols, and aligns with defined API contracts.

## Guidelines

### 1. Test Stack & Organization
- **Primary Tool**: Use **Pest PHP** for a clean, expressive testing syntax.
- **Test Types**:
  - **Feature Tests**: Located in `tests/Feature/[Domain]`. Used for end-to-end API verification.
  - **Unit Tests**: Located in `tests/Unit`. Used for isolated service logic and helper functions.
- **Naming Convention**: Files must end in `Test.php` and use descriptive, action-oriented names (e.g., `GeneratePayrollTest.php`).

### 2. Multi-Tenancy Isolation (P0)
- **Mandatory**: Every feature test involving data persistence MUST verify that records are scoped to the correct tenant.
- **Testing Isolation**:
  ```php
  it('prevents tenant A from accessing tenant B data', function () {
      $tenantA = createTenant();
      $tenantB = createTenant();
      
      $recordA = $tenantA->run(fn() => Invoice::factory()->create());
      
      $tenantB->run(fn() => 
          $this->getJson("/api/invoices/{$recordA->id}")
               ->assertStatus(404)
      );
  });
  ```
- **Helper**: Use the `initializeTenancy()` helper to set up the environment before running assertions.

### 3. API Verification Standards
- **Status Codes**: Explicitly test for successful (200/201), validation failure (422), and authorization failure (403/401) scenarios.
- **Response Structure**: Use `assertJsonStructure` to ensure the API response matches the Postman collection specification.
- **Resource Classes**: Verify that models are correctly transformed via Laravel API Resources.

### 4. Data Management
- **Factories**: Use Model Factories for all data generation. Avoid manual `new Model()` assignments in tests.
- **Database State**: Use the `RefreshDatabase` trait to ensure each test runs in a clean environment.
- **Seeding**: Only use seeds for global configuration data (e.g., Currency codes, Country lists).

## Best Practices
- **Atomic Assertions**: Each `it()` or `test()` block should focus on one specific behavior or outcome.
- **Audit Validation**: For business-critical operations (Accounting, HR), assert that an entry was created in the `audit_trails` table.
- **Mocking**: Mock external services (e.g., Mail, S3, SMS Gateways) to keep tests fast and deterministic.
- **Strict Typing**: Use type hints for all test helpers and mock objects.

## Troubleshooting
- **Tenant Database Missing**: Ensure `php artisan tenants:migrate --database=testing` has been run if using a physical test database.
- **Auth Persistence**: If using Sanctum, ensure the user is authenticated within the specific tenant context using `actingAs()`.
- **JSON Attribute Mismatch**: Double-check PostgreSQL JSONB casting in models if JSON assertions are failing unexpectedly.
