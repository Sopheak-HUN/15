# Skill: Backend Feature Testing Protocol

## Context
Use this skill when developing new features, refactoring code, or fixing bugs in the Laravel ERP backend. This protocol ensures that all changes are verified against multi-tenant isolation requirements, security policies, and API contracts.

## Guidelines

### 1. Test-Driven Implementation
Before finalizing any feature, you **MUST** ensure it has comprehensive test coverage:
1.  **Locate Domain**: Identify the business domain (e.g., `Accounting`, `Inventory`, `Sales`).
2.  **Scaffold Test**: Create a Pest test file in `tests/Feature/[Domain]/[FeatureName]Test.php`.
3.  **Define Expectations**: Use the project's Postman collection to verify endpoint paths and payload structures.

### 2. Multi-Tenant Validation (P0)
Every feature that handles tenant-specific data **MUST** prove isolation. Follow this sequence in your test:
1.  **Setup**: Create two separate tenants (e.g., `TenantA` and `TenantB`).
2.  **Execution**: Perform the action (Create/Update/Delete) within `TenantA`.
3.  **Isolation Assert**: Attempt to access or modify that same data from `TenantB` and assert a `404 Not Found` or `403 Forbidden` response.
4.  **Example**:
    ```php
    it('ensures invoices are isolated between tenants', function () {
        $tenantA = createTenant();
        $tenantB = createTenant();
        
        $invoice = $tenantA->run(fn() => Invoice::factory()->create());
        
        $tenantB->run(fn() => 
            $this->getJson("/api/invoices/{$invoice->id}")->assertStatus(404)
        );
    });
    ```

### 3. API Contract Enforcement
- **JSON Structure**: Use `assertJsonStructure` to validate that the response matches the expected API resource format.
- **Status Codes**: Always test for success (`200/201`), validation errors (`422`), and unauthorized access (`401/403`).
- **Pagination**: For index endpoints, ensure `links` and `meta` keys are present.

## Implementation Process
1.  **Check Factories**: Ensure all models involved have working factories in `database/factories`.
2.  **Run Tests**: Execute `php artisan test` or `vendor/bin/pest`.
3.  **Verify Audits**: For financial or HR-related changes, add:
    ```php
    $this->assertDatabaseHas('audit_trails', [
        'auditable_type' => Invoice::class,
        'event' => 'created',
    ]);
    ```

## Best Practices
- **Atomic Tests**: Keep tests focused on a single assertion or logical flow.
- **Clean State**: Always use the `Illuminate\Foundation\Testing\RefreshDatabase` trait.
- **Mocking**: Use `Http::fake()` or `Storage::fake()` for external dependencies to maintain speed and reliability.

## Troubleshooting
- **Tenant Context Lost**: If assertions fail, verify that `initializeTenancy()` was called or that the test is running within a `$tenant->run()` closure.
- **Validation Failures**: Check that the factory data satisfies all model-level and database-level constraints.