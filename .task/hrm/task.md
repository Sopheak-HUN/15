# Human Resource Management (HRM) - Detailed Tasks

### 1. Recruitment (Talent Acquisition & ATS)
- [x] Phase 1: Backend Database & Models — `vacancies`, `applications`, `interviews`, `interview_feedbacks` (tenant migration `2026_05_21_110500`). Models under `App\Tenants\Modules\HRM\Models`.
- [x] Phase 2: Backend API & Services — `VacancyController`, `ApplicationController`, `InterviewController`. `RecruitmentService` implements `transitionApplication`, `convertToEmployee` (idempotent, dedupe-by-email, partial-unique-index respected), `bulkConvertToEmployee`, `revertEmployeeConversion` (7-day window), and `generateNextEmployeeId` (`TT-NNNN`, zero-indexed, auto-widens past 9999).
- [x] Phase 3: Frontend UI & Integration
- [ ] Phase 4: Testing & QA

### 2. Workforce Management (Employees)
- [x] Phase 1: Backend Database & Models — `departments`, `positions`, `employees`. `employees` has a partial unique index on `email` for live rows only (terminated/reverted employees free their email for re-hire). Sensitive PII (`national_id`, `bank_account`, `tax_id`, `base_salary`) is `encrypted` via Eloquent casts.
- [x] Phase 2: Backend API & Services — `EmployeeController` (CRUD + restore), `DepartmentController`, `PositionController`. `EmployeeService::terminateEmployee` validates the workflow transition, stamps `termination_date`, and soft-deletes.
- [x] Phase 3: Frontend UI & Integration
- [ ] Phase 4: Testing & QA

### 3. Time Off & Leave Management
- [x] Phase 1: Backend Database & Models — `leave_types`, `leave_balances` (unique on `employee × type × year`), `leave_requests`.
- [x] Phase 2: Backend API & Services — `LeaveTypeController`, `LeaveRequestController` (`approve`, `reject`, `balances`). `LeaveService::submitRequest` locks the matching balance row (`SELECT … FOR UPDATE`) to make concurrent submissions race-safe. `accrue()` is wired for a future scheduled job.
- [x] Phase 3: Frontend UI & Integration
- [ ] Phase 4: Testing & QA

### 4. Payroll & Compensation
- [x] Phase 1: Backend Database & Models — `pay_components`, `employee_pay_components` (per-employee overrides with effective-from/to), `payroll_periods`, `payslips` (unique on `period × employee`).
- [x] Phase 2: Backend API & Services — `PayComponentController`, `PayrollPeriodController` (`process`), `PayslipController`. `PayrollService::processPeriod` is idempotent — re-running recomputes payslips. `fixed` and `percentage_of_base` calculations supported; tax rules are configured as `PayComponent` rows (e.g. an `NSSF` percentage deduction).
- [x] Phase 3: Frontend UI & Integration
- [ ] Phase 4: Testing & QA

### 5. Employee Feedback & Suggestions
- [x] Phase 1: Backend Database & Models — `suggestions` (anonymous-capable: `is_anonymous` flag + nullable `employee_id`).
- [x] Phase 2: Backend API & Services — `SuggestionController` + `SuggestionService`. `Suggestion::toArray` strips submitter identity when `is_anonymous` so even admins can't derive it from the JSON. State machine: new → acknowledged → actioned, with dismiss as a side exit.
- [x] Phase 3: Frontend UI & Integration
- [ ] Phase 4: Testing & QA

### 6. Performance Appraisals & Reviews
- [x] Phase 1: Backend Database & Models — `appraisal_cycles` (rating scale JSON), `appraisals` (unique on `cycle × employee`).
- [x] Phase 2: Backend API & Services — `AppraisalCycleController`, `AppraisalController`. `PerformanceService` handles `submit`, `review`, `close`. Status flow `draft → submitted → reviewed → closed` enforced via WorkflowStatusService.
- [x] Phase 3: Frontend UI & Integration
- [ ] Phase 4: Testing & QA

### 7. Employee Notes & Documentation
- [x] Phase 1: Backend Database & Models — `employee_notes` (with `is_disciplinary` flag), `employee_documents` (file_path + mime + expires_at).
- [x] Phase 2: Backend API & Services — `EmployeeNoteController`, `EmployeeDocumentController` (filter by `expiring_soon=1`).
- [x] Phase 3: Frontend UI & Integration
- [ ] Phase 4: Testing & QA

---

## Cross-cutting foundation shipped alongside Phase 1+2

- **WorkflowStatusService** (`App\Tenants\Modules\IAM\Services\WorkflowStatusService`) — central state-machine resolver consumed by every HRM service. Backed by tenant `workflow_statuses` table with `(module, key)` unique index and JSON `allowed_transitions`. Cached per request; `flushCache()` on mutation. Admin CRUD exposed at `/api/iam/workflow-statuses`.
- **HRM permissions seeded** following the `module.feature.action` convention from `skills/hrm/rules.md` (employee/payroll/leave/performance/recruitment × read/write/delete/export). Super-admin role gets all of them.
- **Default workflow statuses seeded** for `hrm.application`, `hrm.leave`, `hrm.appraisal`, `hrm.vacancy`, `hrm.employee`, `hrm.payroll_period`.
- **Default leave types seeded** — Annual (18 days, accrues), Sick (12 days, no approval), Unpaid (0 days, no accrual).

## Frontend (Phase 3) — what shipped

- **Composable & types**: `frontend/composables/useHrmApi.ts` wraps every `/api/hrm/*` route with typed helpers. `frontend/types/hrm.ts` mirrors the backend resource shapes.
- **i18n**: full `hrm.*` keyspace added to `en.json` + `km.json` (workforce, leave, payroll, recruitment, performance, suggestions, notes/documents). Nav entries translated.
- **Nav**: 6 HRM links added to the sidebar — Employees, Leave, Payroll, Recruitment, Performance, Suggestions.
- **Pages built (10)**:
  - `frontend/pages/hrm/employees/index.vue` — list, search/filter, create/edit dialog with sectioned PII block, terminate-with-reason and restore flows. Rows link to the employee detail page.
  - `frontend/pages/hrm/employees/[id]/index.vue` — per-employee detail with tabs for **Notes**, **Documents** (with expiring-soon filter), and **Leave balances**. CRUD against `/employee-notes` and `/employee-documents` inline.
  - `frontend/pages/hrm/departments/index.vue` — paginated CRUD with parent-department select.
  - `frontend/pages/hrm/positions/index.vue` — CRUD with department filter + salary band fields.
  - `frontend/pages/hrm/leave/index.vue` — tabbed (Requests + Types). Requests tab: filter by status/date range, submit-request dialog, inline approve/reject (with rejection-reason prompt). Types tab: CRUD with default-balance, paid/accrues/approval toggles.
  - `frontend/pages/hrm/payroll/index.vue` — three tabs (Periods + Components + Payslips). Periods tab: create + "Run payroll" with confirmation; Components tab: CRUD with kind/calculation selects; Payslips tab: filter by period, opens a detail dialog with the line-items breakdown.
  - `frontend/pages/hrm/recruitment/index.vue` — three tabs (Vacancies + Applications + Interviews). Applications has multi-select for **bulk convert-to-employee**, single-row **convert** + **revert** (within 7-day window), and a status-transition dialog. Interviews tab schedules and lists per application.
  - `frontend/pages/hrm/performance/index.vue` — two tabs (Cycles + Appraisals). Appraisals action column adapts to the workflow status: draft → submit, submitted → review (opens dialog for comments + score), reviewed → close.
  - `frontend/pages/hrm/suggestions/index.vue` — list with status/category filters, submit dialog with **anonymous toggle**, per-state transition dialogs (acknowledge / action / dismiss with response field).

## What still needs your hands

- Run `php artisan tenants:migrate` to apply the 9 new tenant migrations.
- Run `php artisan tenants:seed` to seed permissions, workflow statuses, and default leave types into existing tenants.
- Run `cd frontend && npm install && npm run dev` and exercise the new pages against the backend.
- Per-permission policy gating is NOT yet wired on HRM routes — the `auth:api` middleware authenticates the user, but `module.feature.action` checks are tracked separately (will need `PolicyServiceProvider` bindings or a `can:hrm.x.write` middleware on each route). The frontend currently shows every action to every authenticated user.
- Salary fields and `national_id`/`bank_account` use Eloquent's `encrypted` cast (AES-256-CBC via `APP_KEY`). Field-level encryption per the rules; production deployments must rotate `APP_KEY` carefully or salary values become unrecoverable.
- Tax engine is intentionally minimal — production tenants plug jurisdiction rules in as `PayComponent` rows (`percentage_of_base`/`fixed`, taxable flag).
- eApprovals integration for leave/expenses (see `rules.md`) is a follow-up — currently `LeaveService` runs approval inline via `WorkflowStatusService` transitions.
- Employee document uploads currently store **metadata only** — the actual file upload pipeline (multipart endpoint + `tenant_path()` storage) is a follow-up. The dialog accepts a `file_path` field assuming the file is already in tenant storage.
