# Testing Strategy: Inventory Management

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Warehouses and Stock are strictly private to the tenant. |
| **P0** | **Atomicity** | Stock transfers must be transaction-safe (no partial moves). |
| **P1** | **Thresholds** | Low-stock alerts must be triggered based on tenant config. |
| **P1** | **API Contract** | Follows `erp_collection.json`; uses `lastTransferId`. |
| **P2** | **Suppliers** | Procurement POs must link correctly to vendor profiles. |

## 2. Backend Testing (Pest PHP)

### Atomic Operations (P0)
- **Rule**: Stock transfers must be atomic. Both "Decrement From" and "Increment To" must succeed or both must fail.
- **Test Case**: Simulate a failure in the second step and verify stock levels remain unchanged.

### Multi-Tenancy
- **Rule**: Warehouse and stock data are tenant-isolated.
- **Test Case**: Assert Tenant A cannot see stock levels for Tenant B.

### Procurement
- **Rule**: Receiving goods from a PO must increment stock and set PO status to `received`.
- **Test Case**: Verify stock update after successful PO receipt.

## 2. Postman Verification
- **Collection**: `postman.json`
- **UX**: Ensure the `tenant` header is consistently used in all stock adjustments.

## 3. Alerts
- **Assertion**: Low-stock notifications are triggered when threshold is breached.
- **Test Case**: Decrement stock below threshold and verify `Notification` is sent.
