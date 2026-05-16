# Testing Strategy: Project Management

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Projects and Tasks must be isolated; no cross-tenant visibility. |
| **P0** | **Authorization** | Only project members can update or comment on tasks. |
| **P1** | **Collaboration** | Task status changes must broadcast via WebSockets. |
| **P1** | **API Contract** | Follows `erp_collection.json`; uses `lastTaskId`. |
| **P2** | **Budgeting** | Labor costs must match the employee rates in HRM. |

## 2. Backend Testing (Pest PHP)

### Data Isolation (P0)
- **Rule**: Project data is strictly isolated by `tenant_id`.
- **Test Case**: Assert cross-tenant task updates are blocked with `404`.

### Collaboration
- **Rule**: Task comments must be visible to all assigned members.
- **Test Case**: Add a comment and verify visibility for other project members in the same tenant.

### Resource Budgeting
- **Rule**: Labor costs must be correctly calculated from logged hours.
- **Test Case**: Unit test `ProjectService::calculateCost()` with various hourly rates.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Scenarios**: Test task creation, assignment, and status transitions.

## 3. WebSockets
- **Assertion**: Task status updates must broadcast to the project channel.
- **Test Case**: Use `Event::fake()` to assert the broadcast event.
