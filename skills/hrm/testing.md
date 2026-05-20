# Testing Strategy: Human Resource Management (HRM)

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Employee PII, salaries, and candidate quiz attempts must be strictly scoped to `tenant_id`. |
| **P0** | **Privacy & Security**| Magic-link tokens must be cryptographically secure; candidates must be strictly sandboxed from accessing any central, payroll, or other candidate data (403/404). |
| **P1** | **Calculations** | Payroll engine must correctly apply tax/deduction logic. Quiz auto-grader must correctly score multi-choice/short-answers. |
| **P1** | **API Contract** | Synchronized with `erp_collection.json`; correct answers must never be leaked in active quiz responses. |
| **P2** | **Integration** | Leave requests trigger `eApprovals` workflows; quiz submissions transition the application pipeline status. |

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

### Candidate Quizzing (P0 & P1)
- **Rule**: Candidates using magic-links can only access their allocated quiz questions and attempts. Correct answers are omitted from the quiz payload.
- **Test Case**: Assert candidate endpoint returns `403` when trying to request quiz correct answers or modifying scores directly. Verify completed quiz attempts are immutable.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Tests**: Verify that sensitive fields (Salary, National ID) are omitted in the standard list response, and active quiz payloads omit correct answers.

## 3. Integration
- **Rule**: Leave requests must trigger an entry in `approvals` module.
- **Test Case**: `assertDatabaseHas('approval_requests', ['module' => 'hrm'])`.
- **Rule**: Quiz submissions must automatically transition candidate application status.
- **Test Case**: Assert the application status transitions to `assessment_completed` upon quiz submit.
