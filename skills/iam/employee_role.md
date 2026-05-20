---
name: employee-role-self-service
description: Standardized Employee role and login configurations for secure self-service portal access.
---
# Employee Role & Self-Service

Use this skill to understand, configure, and manage standard **Employee** self-service roles and accounts. This standardizes how rank-and-file employees access the ERP to request leaves, view payslips, and manage their personal profiles while remaining strictly isolated.

## 1. Default Demo Account Credentials
For development, testing, and QA validation, a standard self-service account is seeded within every tenant database:

* **Email/Username**: `role.base@tanent.com`
* **Password**: `password`
* **Assigned Role**: `Employee` (`slug: employee`)
* **Linked Record**: Associated directly with `EMP-001` (`first_name: Base`, `last_name: Employee`)

## 2. Standardized Permissions & RBAC
The `Employee` role is highly restricted to enforce the principle of least privilege. It has access only to employee self-service features:

| Permission Name | Permission Slug | Module | Description |
| :--- | :--- | :--- | :--- |
| **Read Leaves** | `hrm.leave.read` | `hrm` | Allows employees to view their own leave request history. |
| **Write Leaves** | `hrm.leave.write` | `hrm` | Allows employees to request new leaves (medical, annual, etc.). |
| **Read Appraisals** | `hrm.performance.read` | `hrm` | Allows employees to view their own performance appraisals and feedback. |
| **Read Payroll/Payslips** | `hrm.payroll.read` | `hrm` | Allows employees to view and download their personal monthly payslips. |

> [!IMPORTANT]
> **No Administrative Powers**: The Employee role does NOT have administrative permissions such as `hrm.employee.read` or `hrm.employee.write`. They cannot view list views of other employees or access general administrative actions.

## 3. Self-Service Ownership Scoping (Policies)
Ownership validation is enforced at the Laravel Policy and API levels:
* **Route Isolation**: When an employee queries `/api/v1/employees/{id}`, the corresponding `EmployeePolicy` asserts that the requested `{id}` matches the logged-in user's linked employee record.
* **Unauthorized Access**: Querying any other employee profile ID or attempting to list all records (`/api/v1/employees`) will immediately abort with a `403 Forbidden` response.

## 4. Best Practices & Validation
* **Strict Tenancy Scope**: Just like administrative users, the self-service requests must always contain the active `X-Tenant-Handle` header.
* **Safe Auditing**: Any modifications made by an employee to their own profile or leave status must generate an immutable entry in the `audit_logs` table.
