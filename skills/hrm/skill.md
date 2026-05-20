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

## Guidelines

### 1. Workforce Management
- **Employee Lifecycle**: Implement Hire -> Onboard -> Promote -> Offboard workflows.
- **Data Privacy**: Encrypt sensitive fields like Salary, National ID, and Bank Account details.

### 2. Payroll Engine
- **Calculations**: Standardize earnings (Base, Bonus) and deductions (Tax, Insurance) logic.
- **Payslips**: Generate secure, non-editable PDF payslips accessible via the Employee Self-Service portal.

### 3. Leave & Attendance
- **Accrual Logic**: Automatically calculate leave balances based on tenure and tenant policy.
- **Approvals**: Integrate with `eApprovals` for multi-level leave authorization.

## Best Practices
- **Employee Self-Service (ESS)**: Prioritize a clean mobile-first UI for employees to view their own data.
- **Compliance**: Ensure all HR data retention follows local labor laws.
- **Scalability**: Run payroll processing in background jobs (`Laravel Queue`) for large organizations.

## Troubleshooting
- **Incorrect Leave Balance**: Verify the `LeaveAccrualJob` has run for the current period.
- **Payroll Failure**: Check the `PayrollService` logs for missing tax configurations for specific employees.
- **Unauthorized Access**: If an employee can see another's salary, immediately audit the `EmployeePolicy` and `hrm.employee.read` permission.
