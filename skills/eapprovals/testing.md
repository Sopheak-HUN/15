# Testing Strategy: eApprovals

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Approval requests are strictly private to the assigned tenant. |
| **P0** | **Authorization** | Only the assigned approver can process the request status. |
| **P1** | **State Machine** | Transitions must be valid (e.g. cannot approve a rejected doc). |
| **P1** | **API Contract** | Follows `erp_collection.json` standard for workflow actions. |
| **P2** | **Notifications** | Status changes must trigger real-time WebSocket alerts. |

## 2. Backend Testing (Pest PHP)

### State Machine Integrity (P0)
- **Rule**: A request cannot be "Approved" if it is currently in "Rejected" state.
- **Test Case**: `expect(fn() => $request->approve())->toThrow(IllegalStateTransition::class)`.

### Authorization
- **Rule**: Only the designated approver can approve/reject a request.
- **Test Case**: Attempt to approve a request using another user's token. Assert `403`.

### Tenancy
- **Rule**: Approval workflows are isolated per tenant.
- **Test Case**: Verify Tenant A cannot see Tenant B's pending requests.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Validation**: Ensure all action requests include a comment field.

## 3. Side-Effects
- **Assertion**: Status change must trigger notifications.
- **Test Case**: `Notification::assertSentTo(...)`.
