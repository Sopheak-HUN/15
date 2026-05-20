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
| `quiz`      | `hrm.quiz.read`        | `hrm.quiz.write`        | `hrm.quiz.delete`        | `hrm.quiz.export`        |

## 2. Implementation Standards

### Employee & Payroll Flow
1. **Hire/Onboard**: Create profile and set compensation.
2. **Tracking**: Log time, attendance, and leave requests.
3. **Payroll Prep**: Aggregate earnings and deductions.
4. **Processing**: Execute payroll engine with tax calculations.
5. **Disbursement**: Generate payslips and post bank transfer file.
6. **Compliance**: Archive period data for reporting.
7. **Recruitment History Linkage**: Link all pre-hire candidate documentation (applications, quiz assessments, interviews, panel feedback, offer letters) to the new employee profile upon hire to maintain absolute auditability and onboarding context.
8. **Workforce Registry Enrollment**: Ensure newly hired candidates are instantly enrolled as `active` in the employee database so they appear on all workforce directory list views.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\HRM`
- **Service Layer**: Logic in `Services/PayrollService.php`, `Services/LeaveService.php`.
- **Candidate-to-Employee Linkage**: When a candidate is hired, the successful `Application` must be linked to an `Employee` via `applications.employee_id` so the employee profile can traverse the complete pre-hire record (quizzes, structured interview scores, feedback panels, signed offers). **The linkage is a deliberate two-step flow, not a side-effect of the status transition** — see "Hire → Employee Conversion Contract" below.
  - *Employee List Enrollment*: The conversion service must call `EmployeeService::createEmployee` with the initial `active` workflow status. This ensures that the newly created record is immediately searchable and visible in `GET /api/v1/employees` (the workforce directory screen).

- **Hire → Employee Conversion Contract**: Transitioning an application to `hired` only changes status — it does **not** auto-create an employee. `RecruitmentService::transitionApplication` must stay free of `Employee::create()`-side-effects. Conversion happens via dedicated endpoints so it's auditable, idempotent, and reversible within a bounded window.
  - **Single convert** — `POST /applications/{application}/convert-to-employee` → `RecruitmentService::convertToEmployee(Application)`. Requires both `hrm.recruitment.write` AND `hrm.employee.write` (policy `ApplicationPolicy::convert`). Idempotent: if `employee_id` is already set, returns the linked employee without creating a duplicate. Email is the dedupe key — an existing `Employee` with the same email is reused instead of cloned. Stamps `applications.converted_at = now()` on link.
  - **Bulk convert** — `POST /applications/bulk-convert-to-employee` with `{ ids: string[] }` (1–200 UUIDs). Returns `{ converted, alreadyLinked, ineligible, missing, errors }` so the UI can surface partial outcomes without per-row 404 noise. Each row is gated through the same policy; any row not in `hired` lands in `ineligible`, any row already linked lands in `alreadyLinked`.
  - **Revert conversion** — `POST /applications/{application}/revert-employee-conversion` → `RecruitmentService::revertEmployeeConversion(Application)`. Refuses with 422 unless: status=`hired`, `employee_id` set, AND `converted_at` ≤ `RecruitmentService::REVERT_CONVERSION_WINDOW_DAYS` (7 days). Requires `hrm.recruitment.write` AND `hrm.employee.delete` (policy `ApplicationPolicy::revertConversion` — stricter than `convert` because it soft-deletes a workforce record). Soft-deletes the linked `Employee` (preserves audit trail; the next `convertToEmployee` creates a fresh row since `withTrashed()` is not used in the email lookup) and nulls both `employee_id` and `converted_at`. Outside the window, recruiters must use the normal off-boarding flow (`EmployeeService::terminateEmployee`).
  - **Schema** — `applications.converted_at TIMESTAMP NULL` (migration `2024_01_01_000025_add_converted_at_to_applications_table.php`). The `Application` model casts it as `datetime` and exposes it as `convertedAt` in `ApplicationResource`. Frontend reads this to decide whether to render the revert affordance.
- **Privacy**: Employee sensitive data must be encrypted at rest *and* gated at the Resource layer. The `Employee` model casts `base_salary`, `bank_name`, `bank_account_name`, `bank_account_number` via `App\Models\Casts\EncryptedWithFallback` (Laravel ciphertext stored in `text` columns; falls back to returning the raw value on `DecryptException` so legacy/seeded plaintext rows don't 500 list endpoints — re-encryption happens on the next UPDATE). `EmployeeResource` returns them only to callers with `hrm.payroll.read` and masks `bank_account_number` to the last 4 digits. New PII fields must follow the same dual-layer pattern — never rely on Resource masking alone, and never use stock `'encrypted'` cast for fields that may hold mixed/legacy data; use `EncryptedWithFallback` instead.
- **Authorization**: Eloquent Policies are the source of truth (`EmployeePolicy`, `LeavePolicy`, `LeaveTypePolicy`, plus existing `UserPolicy`/`RolePolicy`). Register every new policy in `TenantServiceProvider::boot()` and call `$this->authorize(...)` from controllers — never inline `$user->can()` checks for CRUD gates. FormRequest `authorize()` should usually `return true` so the controller-level policy fires.
  - *Self-Service Access*: Regular employees must be allowed to view their own profile details. In `EmployeePolicy::view`, short-circuit and return `true` if the authenticated user's linked employee record matches the requested employee (`$user->employee?->id === $employee->id`), allowing them to view their details without the broader administrative `hrm.employee.read` permission.
- **Workflows**: Use `eApprovals` integration for leave and expense requests. When a tenant has an `ApprovalWorkflow` with `module=hrm, type=leave`, `LeaveService::submitRequest` automatically opens an `ApprovalRequest`. Decisions flow through `POST /api/v1/approval-requests/{id}/process`; `ApprovalService` dispatches `ApprovalRequestFinalized`, and the `SyncLeaveFromApproval` listener flips the `Leave.status` via `LeaveService::syncFromApproval()`. The legacy `/leaves/{id}/approve|reject` endpoints remain only as a stop-gap for tenants without a configured workflow.
- **FMS posting**: `PayrollService::closePeriod()` aggregates gross/tax/nssf across payslips and posts a balanced accrual journal (`Dr EXP-WAGES / Cr LIA-TAX / Cr LIA-NSSF / Cr LIA-WAGES`) via `AccountingService::postEntry()` inside one DB transaction. Account codes come from `config('payroll.accounts')` (env-overridable). Missing codes raise `DomainException` → 422 listing what to create; the close rolls back so the period stays in `processed` for retry. Reference is `PAYROLL-{period_id}` — idempotent against accidental double-close.
- **Quiz Assessment (Phase 6)**: Magic-link only. Admin assigns a published `Quiz` to an `Application` via `POST /applications/{id}/quiz-attempts`; `QuizService` returns the raw token **once** and persists only its SHA-256 hash on the `quiz_attempt`. Candidate endpoints live outside `auth:api` and authenticate purely on `?token=...`. Correct answers live as Laravel-encrypted ciphertext on `quiz_questions.correct_answer` and are *never* serialised into the candidate-facing payload — only `QuizResource` exposes them, and only when the caller holds `hrm.quiz.write`. Auto-grading flips the linked `Application` to `assessment_completed` (must exist in `workflow_statuses` for `hrm.application` — seeded by default).
- **Public Careers surface**: Candidate-facing endpoints (`/api/v1/public/job-vacancies`, `/public/job-vacancies/{id}`, `POST /public/applications`) live outside `auth:api` but inside the tenant-scoped middleware group, so `X-Tenant-Handle` is still mandatory. Hard rule: public listings + show MUST filter `status=open`; submission MUST reject anything else with 422. Never expose vacancies in `draft`/`paused`/`closed`/`filled` through the public surface — admin routes are the only path for those.
- **Interviewing (Phase 7)**: Interviews use a 3-table schema — `interviews` (lifecycle), `interview_interviewer` (M:N assignment pivot), `interview_feedback` (unique row per interviewer per interview). `InterviewPolicy::submitFeedback` short-circuits for assigned interviewers — they DO NOT need `hrm.recruitment.write` to score their own panels. Scheduling best-effort moves the linked Application to `interview` via `WorkflowStatusService` (swallows disallowed transitions). HR finalises hire/reject via `/applications/{id}/status`, never through the interview lifecycle. Calendar invites ship as RFC 5545 ICS files (`/interviews/{id}/invite.ics`); Google/Outlook OAuth is deferred — do not call third-party calendar APIs from `CalendarSyncService` until that integration lands.

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
| `hrm.quiz_attempt` | `invited` | `completed`, `expired` | `QuizService::startAttempt` / `submitAttempt` |

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
- **Candidate Assessment Portal**: Dedicated sandboxed `/candidate/quiz` route authenticating via secure magic-link token (`GET /api/v1/candidate/auth?token=...` which exchanges token for a limited JWT scope).
