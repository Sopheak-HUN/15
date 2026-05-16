# Testing Strategy: Sales Module

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Orders and Customers must be strictly scoped to the `tenant_id`. |
| **P0** | **Transaction** | Order creation must be atomic with inventory deduction. |
| **P1** | **API Contract** | Synchronized with `erp_collection.json`; uses `{{token}}`. |
| **P1** | **Workflow** | Order status transitions (Pending -> Confirmed) must follow rules. |
| **P2** | **Export** | Sales reports must be exportable via `sales.orders.export`. |

## 2. Backend Testing (Pest PHP)

### Tenancy Isolation (P0)
- **Rule**: Sales orders must be strictly isolated. Tenant A cannot view Tenant B's customers or orders.
- **Test Case**: Assert `404` when fetching an order ID belonging to another tenant.

### Business Logic (Service Layer)
- **Rule**: Creating an order must decrement stock in the Inventory module.
- **Test Case**: `expect($product->fresh()->stock)->toBe($initialStock - $quantity)`.

### Atomic Transactions
- **Rule**: If payment fails, the order status must remain `Pending` or roll back.
- **Test Case**: Simulate a failure and verify the database state.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Scenarios**: Test the full O2C flow: Lead -> Quote -> Order -> Invoice.

## 3. Data Integrity
- **Assertion**: Customer UUIDs must be valid and exist in the tenant's database before order creation.
