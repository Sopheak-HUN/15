# Financial Management (FMS) Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `fms`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `ledger` | `fms.ledger.read` | `fms.ledger.write` | - | `fms.ledger.export` |
| `accounts` | `fms.accounts.read` | `fms.accounts.write` | `fms.accounts.delete` | `fms.accounts.export` |
| `payable` | `fms.payable.read` | `fms.payable.write` | `fms.payable.delete` | `fms.payable.export` |
| `receivable` | `fms.receivable.read` | `fms.receivable.write` | `fms.receivable.delete` | `fms.receivable.export` |
| `tax` | `fms.tax.read` | `fms.tax.write` | - | `fms.tax.export` |

## 2. Implementation Standards

### Financial Transaction Flow
1. **Source Capture**: Capture invoice/expense data.
2. **Entry Creation**: Create balanced Journal Entry.
3. **Validation**: Enforce Debit=Credit check.
4. **Posting**: Finalize and post to General Ledger (Immutability starts here).
5. **Reconciliation**: Update Chart of Account balances.
6. **Reporting**: Generate Balance Sheet / P&L from ledger aggregates.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\FMS`
- **Integrity**: Journal entries MUST never be deleted (use reversal entries).
- **Service Layer**: Core logic in `Services/AccountingService.php`.
- **Auditing**: Every financial transaction must be linked to a `tenant_id` and have an audit trail.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/fms/`
- **Visuals**: Use Chart.js or similar via PrimeVue for financial dashboards.
- **Validation**: Strict client-side validation for currency inputs.
