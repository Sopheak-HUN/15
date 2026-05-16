# Testing Strategy: Human Resource Management (HRM)

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Employee PII and Salaries must be scoped to the `tenant_id`. |
| **P0** | **Privacy** | Non-HR users must be blocked from seeing salary data (403). |
| **P1** | **Calculations** | Payroll engine must correctly apply tax/deduction logic. |
| **P1** | **API Contract** | Synchronized with `erp_collection.json`; uses `lastPayrollRunId`. |
| **P2** | **Integration** | Leave requests must successfully trigger `eApprovals` workflows. |

## 2. Backend Testing (Pest PHP)

### Privacy & Isolation (P0)
- **Rule**: Employees can only see their own payslips/details unless they have `hrm.employee.read`.
- **Test Case**: Login as standard employee and attempt to fetch another employee's record. Assert `403`.

### Payroll Logic
- **Rule**: Tax and deduction calculations must be accurate based on tenant rules.
- **Test Case**: Use a unit test for `PayrollService` with a set of mock salary data.

### Tenancy
- **Rule**: Employee records are tenant-scoped.
- **Test Case**: Verify `tenant_id` is automatically applied via the `BelongsToTenant` trait.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Tests**: Verify that sensitive fields (Salary, National ID) are omitted in the standard list response.

## 3. Integration
- **Rule**: Leave requests must trigger an entry in `approvals` module.
- **Test Case**: `assertDatabaseHas('approval_requests', ['module' => 'hrm'])`.
