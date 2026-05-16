# Testing Strategy: Reporting & Analytics

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Reports must NEVER leak data from other tenants (Leakage = P0). |
| **P0** | **Permissions** | Reporting access must respect underlying module permissions. |
| **P1** | **Performance** | Complex analytical queries must complete within 2 seconds. |
| **P1** | **API Contract** | JSON structure must match frontend chart expectations. |
| **P2** | **Scheduling** | Automated report delivery must use the `lastScheduledReportId`. |

## 2. Backend Testing (Pest PHP)

### Data Leakage Prevention (P0)
- **Rule**: Reports must NEVER include data from other tenants.
- **Test Case**: Verify that an aggregate report in Tenant A has exactly zero contribution from Tenant B's tables.

### Performance
- **Rule**: Heavy reports must not timeout the web server.
- **Test Case**: Use `benchmark()` in Pest to ensure complex analytical queries run within 2 seconds.

### Authorization
- **Rule**: Reporting respects module permissions (e.g., no Sales reports for HR users).
- **Test Case**: Assert `403` when requesting a report from a module the user cannot access.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Validation**: Ensure charts receive the correct JSON schema required by the frontend.

## 3. Caching
- **Assertion**: Re-running a report within the cache window must be faster and not hit the database.
- **Test Case**: Use `Cache::shouldReceive('get')` to verify hit.
