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

### 5. Database Connection Isolation (P0)
- **Strict Database Separation**: Tests must NEVER run on the active development (`develop`) or production (`production`) database connections. Running tests on these databases will erase or corrupt persistent data.
- **Dedicated Test DB Configuration**: In `phpunit.xml` (or your `.env.testing` file), always override `DB_DATABASE` with a dedicated testing database name (e.g., `erp_system_test`) or configure a fully isolated test database.
- **Pre-execution Check**: Ensure the active database connection during test execution points exclusively to the test-dedicated database.

## Best Practices
- **Atomic Assertions**: Each `it()` or `test()` block should focus on one specific behavior or outcome.
- **Audit Validation**: For business-critical operations (Accounting, HR), assert that an entry was created in the `audit_trails` table.
- **Mocking**: Mock external services (e.g., Mail, S3, SMS Gateways) to keep tests fast and deterministic.
- **Strict Typing**: Use type hints for all test helpers and mock objects.

## Troubleshooting
- **Missing Configuration or TestCase**: If `php artisan test` fails with a missing/invalid `phpunit.xml.dist` error or a missing `Tests\TestCase` class:
  - Create a standard `phpunit.xml` in the root of the `backend/` directory to define the test runner options and set `DB_DATABASE=erp_system_test` (MUST be a dedicated testing database; NEVER use the active development or production database `erp_system`).
  - Create a base `tests/TestCase.php` class extending `Illuminate\Foundation\Testing\TestCase`.
- **Relation "tenants" does not exist**: Because central and tenant migrations are decoupled (`database/migrations/central` vs `database/migrations/tenant`), standard `RefreshDatabase` commands will only execute root-level migrations.
  - Due to PHP trait precedence, a trait imported directly on a test subclass (e.g., `use RefreshDatabase;`) will override inherited methods from a base `TestCase` class. To resolve this, define the `migrateFreshUsing()` method directly on your test class that imports the trait:
    ```php
    protected function migrateFreshUsing()
    {
        return [
            '--path' => [
                'database/migrations/central',
                'database/migrations',
                'database/migrations/tenant',
            ],
            '--drop-views' => $this->shouldDropViews(),
            '--drop-types' => $this->shouldDropTypes(),
        ];
    }
    ```
- **Tenant Database Missing**: Ensure `php artisan tenants:migrate --database=testing` has been run if using a physical test database.
- **Auth Persistence**: If using Sanctum, ensure the user is authenticated within the specific tenant context using `actingAs()`.
- **JSON Attribute Mismatch**: Double-check PostgreSQL JSONB casting in models if JSON assertions are failing unexpectedly.
