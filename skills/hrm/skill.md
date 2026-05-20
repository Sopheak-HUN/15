---
name: human-resource-management
description: Build employee management systems, payroll engines, leave tracking, and performance appraisals.
---
# Human Resource Management (HRM)

Use this skill when building employee management systems, payroll engines, leave tracking, or performance appraisal modules. This module handles sensitive PII (Personally Identifiable Information).

## Workflows
1. **Employee Onboarding**: Manage the transition from candidate to employee, including contract generation and system access.
2. **Payroll Run**: Orchestrate monthly salary calculations, including taxes, deductions, and Payslip distribution.
3. **Leave Application**: Handle the end-to-end process of leave requests, from submission to balance adjustment.
4. **Candidate Quiz Assessment**: Secure magic-link generation, email invitation, sandboxed quiz session, automated evaluation, and integration with the ATS application pipeline.

## Guidelines

### 1. Workforce Management
- **Employee Lifecycle**: Implement Hire -> Onboard -> Promote -> Offboard workflows.
- **Data Privacy**: Encrypt sensitive fields like Salary, National ID, and Bank Account details.
- **Self-Service Details Access**: Allow employees to securely view their own detailed employee profile. Implement ownership validation in policies to permit self-view without administrative read permissions, keeping sensitive fields gated appropriately (e.g. `base_salary` requires `hrm.payroll.read`).
- **Recruitment History Linkage**: When a candidate is hired, link their `Application` record (including quiz attempts, structured interview feedback, panel scores, and digital offer files) to the newly created `Employee` profile to ensure continuous data traceability. **Linkage is explicit, not implicit** — see "Hire → Employee Conversion Contract" in `rules.md` and Stage 4.5 in `recruitment/flow.md`. Status transitions to `hired` never side-effect an Employee creation; conversion is a deliberate `POST /applications/{id}/convert-to-employee` call (single or bulk), reversible for 7 days via `POST /applications/{id}/revert-employee-conversion`.
- **Workforce Registry Visibility**: Upon successful conversion, new employees must be provisioned with status `active` so they immediately display on the employee listing and registry views.

### 2. Payroll Engine
- **Calculations**: Standardize earnings (Base, Bonus) and deductions (Tax, Insurance) logic.
- **Payslips**: Generate secure, non-editable PDF payslips accessible via the Employee Self-Service portal.

### 3. Leave & Attendance
- **Accrual Logic**: Automatically calculate leave balances based on tenure and tenant policy.
- **Approvals**: Integrate with `eApprovals` for multi-level leave authorization.

### 4. Candidate Quizzing & ATS Security
- **Magic-Link Authentication**: Issue a secure, short-lived hash token scoped to `application_id`. Upon verification, grant a temporary, restricted `candidate` role session that permits only reading authorized quiz details and submitting the answers.
- **Sandboxed Assessment**: Quiz interfaces must implement client-side time-tracking, sandboxed navigation, and automated page-blur detection to ensure evaluation integrity.
- **Auto-Grading & ATS Integration**: Automatically compute the final score upon submission, update the application record, and log results into `quiz_attempts` table. Correct answers must be stored in encrypted format to prevent database leakage.

## Best Practices
- **Employee Self-Service (ESS)**: Prioritize a clean mobile-first UI for employees to view their own data.
- **Compliance**: Ensure all HR data retention follows local labor laws.
- **Scalability**: Run payroll processing in background jobs (`Laravel Queue`) for large organizations.

## Troubleshooting
- **Incorrect Leave Balance**: Verify the `LeaveAccrualJob` has run for the current period.
- **Payroll Failure**: Check the `PayrollService` logs for missing tax configurations for specific employees.
- **Unauthorized Access**: If an employee can see another's salary, immediately audit the `EmployeePolicy` and `hrm.employee.read` permission.
