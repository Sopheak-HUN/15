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

### Frontend submit handler — WIRED to `POST /api/hrm/employees`

`onSubmit` in [create.vue](../../frontend/pages/hrm/employees/create.vue)
now calls `hrm.createEmployee(payload)`, toasts on success, and redirects
to `/hrm/employees`. The button drives `:loading="isSubmitting"`.

**Payload mapping** (wizard field → backend column):

| Backend (`employees` table) | Wizard field |
|---|---|
| `first_name`, `last_name`, `email`, `phone` | same |
| `date_of_birth`, `gender`, `bank_account` | same |
| `national_id` | `id_card_number` (only when `identification_type === 'national_id'`) |
| `address` | `home_number, street, village, commune` joined with `, ` |
| `city` | province name (looked up from the loaded `provinces` ref) |
| `country` | hard-coded `'Cambodia'` |
| `department_id`, `position_id` | same |
| `hire_date` | `joined_date` |
| `base_salary` | `salary` |
| `employment_type` | derived from `contract_type` via `employmentTypeFromContract()` (internship → intern, fdc/consulting → contract, else full_time) |

**Server-error handling.** On Laravel 422 the handler reads
`err.data.errors`, maps each server field back to its wizard step via
`SERVER_FIELD_TO_WIZARD`, and jumps to the earliest step that owns one of
those fields. The first error message is shown in an error toast. Aliases
in the map: `hire_date → joined_date`, `base_salary → salary`,
`national_id → id_card_number`, `address → home_number`, `city → province_id`.

### Schema integration shipped — wizard fully persisted (2026-05-26)

The wizard's full nested payload is now persisted. Six new tenant migrations
ship the schema:

| Migration | Tables / changes |
| --- | --- |
| [2026_05_26_160000_extend_employees_for_wizard.php](../../backend/database/migrations/tenant/2026_05_26_160000_extend_employees_for_wizard.php) | Widens `employees` with `first_name_kh`, `last_name_kh`, `nssf_id`, `role_name`, `office_phone`, `contact_phone`, `nationality`, `religion`, `marital_status`, `blood_group`, `children_count`, `identification_type`, `id_card_number` (encrypted), `id_issued_date/by/place` |
| [2026_05_26_160100_create_employee_addresses_table.php](../../backend/database/migrations/tenant/2026_05_26_160100_create_employee_addresses_table.php) | `employee_addresses` — `type ∈ {current, permanent, emergency}`, raw MEF codes for province/district/commune/village, optional `group`, `lat`, `lng`. Unique on `(employee_id, type)` |
| [2026_05_26_160200_create_employee_spouses_table.php](../../backend/database/migrations/tenant/2026_05_26_160200_create_employee_spouses_table.php) | `employee_spouses` (1:1) — name, DOB, education, occupation |
| [2026_05_26_160300_create_employee_emergency_contacts_table.php](../../backend/database/migrations/tenant/2026_05_26_160300_create_employee_emergency_contacts_table.php) | `employee_emergency_contacts` (1:1) — father/mother name+occupation, phone, home_phone. The address lives in `employee_addresses` with `type='emergency'` |
| [2026_05_26_160400_create_employee_educations_table.php](../../backend/database/migrations/tenant/2026_05_26_160400_create_employee_educations_table.php) | `employee_educations` (1:N) — level, major_subject, status, university_school |
| [2026_05_26_160500_create_employee_contracts_table.php](../../backend/database/migrations/tenant/2026_05_26_160500_create_employee_contracts_table.php) | `employee_contracts` (1:N) — type, start/end_date, comment, status (`active`/`expired`/`terminated`). Wizard seeds one row with `status='active'` |

**Models**: [EmployeeAddress](../../backend/app/Tenants/Modules/HRM/Models/EmployeeAddress.php), [EmployeeSpouse](../../backend/app/Tenants/Modules/HRM/Models/EmployeeSpouse.php), [EmployeeEmergencyContact](../../backend/app/Tenants/Modules/HRM/Models/EmployeeEmergencyContact.php), [EmployeeEducation](../../backend/app/Tenants/Modules/HRM/Models/EmployeeEducation.php), [EmployeeContract](../../backend/app/Tenants/Modules/HRM/Models/EmployeeContract.php). 1:1 models use `employee_id` as primary key (no separate UUID); 1:N models use `HasUuids`. `Employee` gains relations: `addresses`, `currentAddress/permanentAddress/emergencyAddress` (filtered HasOne), `spouse`, `emergencyContact`, `educations`, `contracts`, `activeContract`.

**Service**: [EmployeeService::create](../../backend/app/Tenants/Modules/HRM/Services/EmployeeService.php) now accepts a nested payload — carves off `current_address`, `permanent_address`, `emergency_address`, `spouse`, `emergency_contact`, `educations[]`, `contract`, then creates the employee + each related row in a single `DB::transaction`. The `hasAny()` helper skips creating empty sub-rows (so the user can leave optional sections blank without inserting placeholder rows). `EMPLOYEE_COLUMNS` allowlists which top-level keys go to `Employee::create()`; everything else is ignored at that layer.

**Controller**: [EmployeeController](../../backend/app/Tenants/Modules/HRM/Controllers/EmployeeController.php) `rules()` extended with all top-level columns + dotted-path validation for each nested block (e.g. `current_address.province_code` ⇒ `nullable|string|max:16`, `contract.end_date` ⇒ `after_or_equal:contract.start_date`). `show()` now eager-loads `currentAddress`, `permanentAddress`, `emergencyAddress`, `spouse`, `emergencyContact`, `educations`, `activeContract`, `contracts`.

**Frontend**: [create.vue:onSubmit](../../frontend/pages/hrm/employees/create.vue) builds the nested payload directly — `current_address`, `permanent_address`, `emergency_address` each carry the raw MEF codes (since `useCambodiaGeo` returns `code` as `id`, the wizard's `province_id` values ARE the codes). The dotted-path `SERVER_FIELD_TO_WIZARD` map routes 422 errors back to the wizard fields so `current_address.village_code` correctly jumps to step 2.

**Bring it up on a fresh machine**:

```bash
cd backend
docker compose exec app php artisan tenants:migrate
```

### Photo upload — MinIO integration shipped (2026-05-26)

The wizard now uploads the employee photo to MinIO via a presigned PUT
flow. Laravel never streams the file bytes.

**Architecture**

```
 Browser                  Laravel                    MinIO (S3 API)
 ───────                  ───────                    ──────────────
  POST /api/uploads/employee-photo ──► UploadController::employeePhoto
                                       │
                                       │ S3UploadService::signEmployeePhotoPut()
                                       │   public client → http://localhost:9000
                                       ◄── { upload_url, key }
  PUT upload_url (Content-Type: image/jpeg, body: File)
                                                  ──► PutObject uploads/{nanoid}.jpg
  POST /api/hrm/employees { photo_temp_key, ... } ──► EmployeeController::store
                                       │
                                       │ EmployeeService::create()
                                       │ (DB::transaction creates rows)
                                       │ ↓ after commit
                                       │ S3UploadService::commitObject()
                                       │   internal client → http://minio:9000
                                       │   CopyObject  uploads/x.jpg → tenants/{h}/employees/{uuid}/photo.jpg
                                       │   DeleteObject uploads/x.jpg
                                       │ ↓
                                       │ employees.photo_path ← final key
  GET /api/hrm/employees/{id}        ──► photo_url accessor → S3UploadService::signGet (5-min TTL)
                                       ◄── { ..., photo_url: 'http://localhost:9000/erp-uploads/...' }
  <img :src="photo_url">             ──────────────────────► GetObject tenants/{h}/...
```

**Why two S3 clients** ([S3UploadService.php](../../backend/app/Services/S3UploadService.php))

The signing endpoint stamped into the presigned URL has to be the host
the **browser** can reach (`http://localhost:9000`), but actual Laravel
↔ MinIO traffic happens over the Docker network (`http://minio:9000`).
The default `Storage::disk('s3')` uses `AWS_ENDPOINT` for everything, so
the service builds a second `S3Client` pointed at `AWS_PUBLIC_ENDPOINT`
exclusively for `createPresignedRequest()`. Commit ops (copy / delete /
get) keep using the default disk.

**Files shipped**

| File | Purpose |
| --- | --- |
| [docker-compose.yml](../../docker-compose.yml) | New `minio` service (ports 9000/9001) + `minio-init` (creates `erp-uploads` bucket and a 1-day lifecycle rule on the `uploads/` prefix) + S3 env vars on `app` + `queue` |
| [backend/.env.example](../../backend/.env.example) | Documented `AWS_*` + `AWS_PUBLIC_ENDPOINT` |
| [backend/composer.json](../../backend/composer.json) | `league/flysystem-aws-s3-v3 ^3.29` |
| [backend/config/filesystems.php](../../backend/config/filesystems.php) | `s3` disk gains a `public_endpoint` config key (reads `AWS_PUBLIC_ENDPOINT`) |
| [2026_05_26_160600_add_photo_to_employees.php](../../backend/database/migrations/tenant/2026_05_26_160600_add_photo_to_employees.php) | `employees.photo_path` (nullable string) |
| [backend/app/Services/S3UploadService.php](../../backend/app/Services/S3UploadService.php) | `signEmployeePhotoPut`, `signGet`, `commitObject(temp, final)`, `delete` |
| [backend/app/Http/Controllers/UploadController.php](../../backend/app/Http/Controllers/UploadController.php) | `POST /api/uploads/employee-photo` |
| [backend/routes/tenant.php](../../backend/routes/tenant.php) | Route registered inside the `auth:api` + tenant-scope group |
| [backend/app/Tenants/Modules/HRM/Models/Employee.php](../../backend/app/Tenants/Modules/HRM/Models/Employee.php) | `photo_url` accessor (5-min presigned GET, cached per request), appended to JSON |
| [backend/app/Tenants/Modules/HRM/Services/EmployeeService.php](../../backend/app/Tenants/Modules/HRM/Services/EmployeeService.php) | `create()` commits the temp key **after** the DB transaction succeeds; `photo_path` is OUT of `EMPLOYEE_COLUMNS` so callers can't inject arbitrary keys |
| [backend/app/Tenants/Modules/HRM/Controllers/EmployeeController.php](../../backend/app/Tenants/Modules/HRM/Controllers/EmployeeController.php) | `photo_temp_key` rule: `nullable\|string\|max:255\|starts_with:uploads/` |
| [frontend/composables/useUpload.ts](../../frontend/composables/useUpload.ts) | `uploadEmployeePhoto(file)` → presign + raw `fetch` PUT, returns key |
| [frontend/pages/hrm/employees/create.vue](../../frontend/pages/hrm/employees/create.vue) | `onSubmit` uploads photo first, sends `photo_temp_key` in the create payload |

**Security guards**

- Upload endpoint is inside the `auth:api` + tenant middleware group — no
  unauthenticated presigns.
- Mime + size validated by the controller (JPEG/PNG, ≤ 2 MB).
- `EmployeeService::commitObject` refuses any source key that doesn't
  start with `uploads/` — defeats forged `photo_temp_key` values that
  would try to copy from another tenant's prefix.
- Bucket stays private; reads go through 5-minute presigned GET URLs in
  `photo_url`. No public ACLs.
- 1-day lifecycle rule on `uploads/` reclaims abandoned presigned PUTs
  (browser closed mid-upload, validation failed, etc.).

**Run it on a fresh machine**

```bash
docker compose down
docker compose build app           # picks up league/flysystem-aws-s3-v3
docker compose up -d
# wait ~10s for MinIO health + bucket bootstrap to run
docker compose exec app composer install
docker compose exec app php artisan tenants:migrate
# Open the MinIO console at http://localhost:9001
#   user: erp-dev-root  /  pass: erp-dev-secret
```

**Production swap** — set `AWS_BUCKET`, `AWS_ENDPOINT`, `AWS_PUBLIC_ENDPOINT`
to the prod target (R2, S3, Spaces) and remove the `minio` services from
`docker-compose.yml`. Use scoped IAM credentials, never bucket root keys.

### What still needs your hands

- Run `docker compose exec app php artisan tenants:migrate` to apply the
  6 new tenant migrations on existing tenants.
- Test the full submit against a fresh employee — verify rows land in
  `employees`, `employee_addresses` (3 rows: current/permanent/emergency),
  `employee_spouses`, `employee_emergency_contacts`, `employee_educations`,
  `employee_contracts`.
- Pest tests for the integration (P0 isolation + transaction rollback on
  partial failure) are still outstanding.
- The employee detail page ([[id]/index.vue](../../frontend/pages/hrm/employees/[id]/index.vue))
  doesn't yet render the new sections — it only shows notes / documents /
  leave balances. Wiring those tabs is a follow-up.
