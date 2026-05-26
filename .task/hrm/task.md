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

---

## Session log: Employee creation wizard (2026-05-26)

Rebuilt the previous single-page create form as a **7-step wizard** at
[frontend/pages/hrm/employees/create.vue](../../frontend/pages/hrm/employees/create.vue).
The page navigates Province → District → Commune → Village via the new
shared Cambodia geo composable (see `.task/geo/task.md`).

### Steps shipped

1. **Basic info** — first/last name (EN + KH), employee ID (auto), gender,
   date of birth, joined date (defaults to today), NSSF ID, department,
   position (filtered by department), nationality, role name, office phone,
   phone, email, bank account, salary, photo upload with preview.
2. **Current address** — home number, street, province/district/commune/village
   cascade, lat/lng with Google Maps iframe embed and "Use my current location"
   geolocation button.
3. **Permanent address** — same shape as Step 2 with a **"Same as current
   address" checkbox** that copies all 8 values in cascade-aware order
   (set parent → await child fetch → set child) and disables the inputs.
   Reactive watcher keeps the two sides in lockstep while the toggle is on.
4. **Contact information** — Type of Identification (national_id/passport/etc.),
   ID card number, issued date/by/place, religion, marital status, blood
   group, spouse name/DOB/education/occupation, children count, contact
   phone (intentionally separate from Step 1's `phone` field).
5. **Relative / Emergency contact** — father/mother name + occupation,
   home/street, full Cambodia geo cascade (independent state `er_*`),
   group, phone number, home phone.
6. **Education detail** — education level (dual-language options like
   "Doctor of Philosophy / បណ្ឌិត"), major subject, education status,
   university/school.
7. **Employee contract (final)** — contract type (dual-language: Work
   Contract / FDC / UDC / Probation / Internship / Consulting), start
   date, end date, comment. Submit button replaces "Next"; on validation
   failure the wizard jumps back to the earliest invalid step with a
   pointing toast.

### Cross-cutting wizard mechanics

- **Zod schema is `computed`** so error messages re-translate on locale
  switch (`hrm.common.required` → `ទាមទារ` in KH).
- **`reqStr()` helper** sets both `required_error` and `invalid_type_error`
  so fields whose initial value is `null`/`undefined` still show our
  translated message (otherwise Zod's English default "Required" leaked).
- **Per-step validation** via `validateField` + a `STEP_KEYS` map — only
  the current step's keys are validated when the user clicks "Next", so
  Step 1 doesn't block on Step 4 errors.
- **Independent geo refs per address** (current / permanent / emergency)
  so the three dropdown sets don't share options. The shared `provinces`
  list IS reused via `watch(provinces, …, { immediate: true })` to avoid
  refetching 25 provinces three times.
- **Dynamic dropdown height** — `dropdownHeight(count)` returns
  `count*38+8px` up to 10 items, then caps at 380px and lets the virtual
  scroller take over. Virtual scroller is only enabled when items > 10,
  so a 3-village commune shows a snug 122px panel instead of a 380px void.

### i18n additions

- Hundreds of keys added across `en.json` + `km.json` under `hrm.employees.*`
  (fields, placeholders, sections, wizard steps, identificationTypes,
  religions, maritalStatuses, educationLevels).
- `hrm.common.required` = "Required" / "ទាមទារ".
- Cambodian-HR convention for degree levels and contract types uses
  hardcoded dual-language labels ("English / ខ្មែរ") instead of
  per-locale strings since both are always shown together.
- Bonus: `firstName` / `lastName` in `km.json` now render as
  `នាមឡាតាំង` / `នាមត្រកូលឡាតាំង` (Latin-script names) so the KH UI
  distinguishes them from the `firstNameKh` / `lastNameKh` fields.

### Layout fix

- [frontend/layouts/default.vue](../../frontend/layouts/default.vue):
  root container switched from `min-h-screen` to `h-screen overflow-hidden`
  so the sidebar and topbar stay pinned and only the `<main>` scrolls.

### Frontend submit handler — STILL A PLACEHOLDER

`onSubmit` in `create.vue` currently just toasts and `console.log`s the
gathered payload. Backend `POST /api/employees` payload mapping has NOT
been wired — when ready, replace the toast block with a real
`hrm.createEmployee(payload)` call. The cross-step validation jump-back
will keep working.
