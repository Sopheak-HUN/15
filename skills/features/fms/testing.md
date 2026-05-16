# Testing Strategy: Financial Management (FMS)

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Ledger and Accounts must be isolated; no cross-tenant leakage. |
| **P0** | **Integrity** | Debits MUST equal Credits for every journal entry. |
| **P1** | **Immutability** | Posted journal entries cannot be deleted (only reversed). |
| **P1** | **API Contract** | Follows `erp_collection.json` standards for financial data. |
| **P2** | **Reporting** | Balance Sheet and P&L must accurately aggregate tenant data. |

## 2. Backend Testing (Pest PHP)

### Data Integrity (P0)
- **Rule**: Debits must always equal credits.
- **Test Case**: Assert an exception or `422` error if a journal entry is unbalanced.

### Multi-Tenancy
- **Rule**: Ledger data must be scoped to the `tenant_id`.
- **Test Case**: Verify that a "Balance Sheet" report only includes accounts for the active tenant.

### Immutability
- **Rule**: Finalized journal entries cannot be deleted.
- **Test Case**: Assert `403` or custom error when attempting to call `DELETE /api/fms/ledger/{id}` on a posted entry.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Validation**: Ensure all currency amounts are sent as high-precision decimals.

## 3. Integration Testing
- **Rule**: Invoices from Sales/HRM must trigger automatic GL entries.
- **Test Case**: Create an invoice and verify the `fms_ledger` table update.
