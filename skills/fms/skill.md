# Financial Management (FMS)

Use this skill when implementing accounting logic, general ledger entries, tax compliance, or financial reporting. Accuracy and data integrity are paramount in this module.

## Guidelines

### 1. General Ledger (GL)
- **Immutable Entries**: Journal entries must never be modified or deleted. Use reversal entries for corrections.
- **Double-Entry**: Every transaction MUST have equal debits and credits.

### 2. Accounts Payable & Receivable (AP/AR)
- **Aging Reports**: Implement logic to track overdue payments and receivables automatically.
- **Bank Reconciliation**: Ensure bank statements can be imported and matched with internal records.

### 3. Tax Compliance
- **Multi-Jurisdiction**: Support dynamic tax calculation rules based on tenant configuration.
- **Reporting**: Generate VAT/GST reports that comply with local regulations.

## Best Practices
- **Audit Trail**: Every financial movement must be linked to a source document (Invoice, Receipt, or Journal Voucher).
- **Currency Handling**: Use a dedicated Money library or high-precision decimals (DECIMAL(19,4)) for all financial values.
- **Security**: Finance operations require the highest level of RBAC (`fms.ledger.write`).

## Troubleshooting
- **Unbalanced Ledger**: If debits don't match credits, check the `AccountingService` for unhandled edge cases in transaction splits.
- **Missing Transactions**: Ensure that `DB::transaction()` is used in all service methods that affect multiple ledger accounts.
- **Performance**: Analytical reports (Balance Sheet, P&L) should be optimized with materialized views or dedicated read-replicas.
