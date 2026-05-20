# Human Resource Management (HRM) Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `hrm`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `employee` | `hrm.employee.read` | `hrm.employee.write` | `hrm.employee.delete` | `hrm.employee.export` |
| `payroll` | `hrm.payroll.read` | `hrm.payroll.write` | - | `hrm.payroll.export` |
| `leave` | `hrm.leave.read` | `hrm.leave.write` | `hrm.leave.delete` | `hrm.leave.export` |
| `performance`| `hrm.performance.read` | `hrm.performance.write` | - | `hrm.performance.export` |
| `recruitment`| `hrm.recruitment.read` | `hrm.recruitment.write` | `hrm.recruitment.delete` | `hrm.recruitment.export` |

## 2. Implementation Standards

### Employee & Payroll Flow
1. **Hire/Onboard**: Create profile and set compensation.
2. **Tracking**: Log time, attendance, and leave requests.
3. **Payroll Prep**: Aggregate earnings and deductions.
4. **Processing**: Execute payroll engine with tax calculations.
5. **Disbursement**: Generate payslips and post bank transfer file.
6. **Compliance**: Archive period data for reporting.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\HRM`
- **Service Layer**: Logic in `Services/PayrollService.php`, `Services/LeaveService.php`.
- **Privacy**: Employee sensitive data (salary, documents) must be encrypted or strictly scoped.
- **Workflows**: Use `eApprovals` integration for leave and expense requests.

### Status Flows (Configurable per tenant)
All HRM lifecycle statuses are stored in the central `workflow_statuses` table and resolved at runtime by `App\Tenants\Modules\IAM\Services\WorkflowStatusService`. **Do NOT add `const STATUS_FLOW = [...]` to domain models.**

| Module key | Initial | Terminal | Used by |
|---|---|---|---|
| `hrm.application` | `applied` | `hired`, `rejected`, `withdrawn` | `RecruitmentService::transitionApplication` |
| `hrm.leave` | `pending` | `approved`, `rejected` | `LeaveService::approve` / `reject` |
| `hrm.appraisal` | `draft` | `closed` | `PerformanceService::submit` / `review` / `close` |
| `hrm.vacancy` | `draft` | `closed`, `filled` | `RecruitmentService::publishVacancy` / `closeVacancy` |
| `hrm.employee` | `active` | `terminated` | `EmployeeService::terminateEmployee` |
| `hrm.payroll_period` | `draft` | `closed` | `PayrollService::processPeriod` / `closePeriod` |

Service contracts:
- `$statuses->initialFor($module): string` — bootstrap status when creating a record. Inject `WorkflowStatusService` into the constructor instead of hardcoding.
- `$statuses->validateTransition($module, $from, $to): void` — throws `DomainException` on invalid moves; the controller catches and returns 422.
- `$statuses->lookup($module, $key): ?WorkflowStatus` — fetch a single row (label/color/icon).
- `$statuses->flushCache()` — call after mutating the table.

Defaults are seeded by `TenantDatabaseSeeder::seedWorkflowStatuses()` (idempotent). Tenant admins can rename labels, change colors/icons, reorder, or add new statuses via `GET/POST/PUT/DELETE /api/v1/workflow-statuses`. Removing a terminal-only status is safe; removing a status that's still referenced by live records will leave those records with an unknown status — transition validation then fails fast.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/hrm/`
- **Self-Service**: Implement a dedicated `/me` portal for employees to view payslips and apply for leave.
- **Directives**: Hide sensitive compensation data using `v-can="'hrm.payroll.read'"` or similar.
