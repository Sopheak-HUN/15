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

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/hrm/`
- **Self-Service**: Implement a dedicated `/me` portal for employees to view payslips and apply for leave.
- **Directives**: Hide sensitive compensation data using `v-can="'hrm.payroll.read'"` or similar.
