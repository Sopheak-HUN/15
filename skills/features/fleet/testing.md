# Testing Strategy: Fleet Management

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Vehicle and fuel logs must be strictly isolated per tenant. |
| **P0** | **Integrity** | Maintenance schedules must be generated reliably for mileage. |
| **P1** | **API Contract** | Webhook listeners must verify signatures/headers. |
| **P1** | **Calculations** | TCO and fuel efficiency formulas must be unit-tested. |
| **P2** | **Alerts** | Maintenance reminders must reach the correct fleet manager. |

## 2. Backend Testing (Pest PHP)

### Asset Isolation (P0)
- **Rule**: Vehicle data must be tenant-scoped.
- **Test Case**: Assert that a request to fetch vehicle data across tenants is blocked.

### Integration
- **Rule**: Telematics webhooks must be authenticated.
- **Test Case**: Send a mock webhook with an invalid signature and assert `401`.

### Calculations
- **Rule**: Fuel efficiency (km/l) must be calculated correctly in `VehicleService`.
- **Test Case**: Unit test with predefined mileage and fuel logs.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Examples**: Provide valid VIN and registration number formats.

## 3. Scheduling
- **Assertion**: Maintenance alerts must be generated when mileage thresholds are hit.
- **Test Case**: Manually trigger `MaintenanceSchedulerJob` and verify database entries.
