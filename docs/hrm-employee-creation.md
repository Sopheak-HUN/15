# HRM — Employee Creation Wizard

How the 7-step Nuxt wizard at
[`frontend/pages/hrm/employees/create.vue`](../frontend/pages/hrm/employees/create.vue)
talks to the Laravel backend at `POST /api/hrm/employees`, including
the structured Cambodia geography cascade and the MinIO photo upload.

## 1. The wizard

| Step | Title | What's captured |
|---|---|---|
| 1 | Basic info | first/last name (EN + KH), employee ID (auto), gender, DOB, joined date, NSSF ID, department, position, nationality, role name, phones, email, bank account, salary, **photo** |
| 2 | Current address | home number, street, province → district → commune → village cascade, lat/lng (with "use my location" + Google Maps embed) |
| 3 | Permanent address | same shape as step 2 with a **"Same as current address"** checkbox |
| 4 | Contact information | ID type, ID card number, issued date/by/place, religion, marital status, blood group, spouse name/DOB/education/occupation, children count, contact phone |
| 5 | Emergency contact | father/mother name + occupation, address (independent geo cascade), group, phone, home phone |
| 6 | Education detail | level, major, status, university/school |
| 7 | Employee contract | type, start/end dates, comment, **submit** |

Cross-cutting wizard mechanics ([create.vue](../frontend/pages/hrm/employees/create.vue)):
- **Zod schema is `computed`** so error messages re-translate on locale switch.
- **Per-step validation** via `STEP_KEYS` map — only the current step's keys are validated on `Next`.
- **Independent geo refs per address** (current / permanent / emergency) so dropdown options don't bleed across sections.
- **Dynamic dropdown height** with virtual scroller (only enabled past 10 rows).
- **`isSubmitting`** drives the Submit button's spinner.

## 2. Cambodia geography

The cascading dropdowns are powered by
[`useCambodiaGeo()`](../frontend/composables/useCambodiaGeo.ts) which
hits the central (landlord) endpoints:

```
GET /api/geo/provinces
GET /api/geo/districts?province_code=01
GET /api/geo/communes?district_code=0102
GET /api/geo/villages?commune_code=010201
```

The composable returns `{ id, name, name_kh, parent_id }` where `id` is the raw **MEF code** (e.g. `'01'`, `'0102'`). These codes ARE the foreign keys we send to the backend in the create payload (no separate ID translation needed).

See [`.task/geo/task.md`](../.task/geo/task.md) for the import pipeline + dataset details.

## 3. Backend schema

7 tenant migrations underpin the wizard ([backend/database/migrations/tenant/](../backend/database/migrations/tenant)):

| Migration | Tables / changes |
|---|---|
| `2026_05_26_160000_extend_employees_for_wizard` | Widens `employees`: `first_name_kh`, `last_name_kh`, `nssf_id`, `role_name`, `office_phone`, `contact_phone`, `nationality`, `religion`, `marital_status`, `blood_group`, `children_count`, `identification_type`, `id_card_number` (encrypted), `id_issued_date/by/place` |
| `2026_05_26_160100_create_employee_addresses_table` | `employee_addresses` — `type ∈ {current, permanent, emergency}`, raw MEF codes for province/district/commune/village, `lat`, `lng`, `group`. Unique `(employee_id, type)` |
| `2026_05_26_160200_create_employee_spouses_table` | `employee_spouses` (1:1) — name, DOB, education, occupation |
| `2026_05_26_160300_create_employee_emergency_contacts_table` | `employee_emergency_contacts` (1:1) — father/mother + phone/home_phone (address goes in `employee_addresses` with `type='emergency'`) |
| `2026_05_26_160400_create_employee_educations_table` | `employee_educations` (1:N) — level, major_subject, status, university_school |
| `2026_05_26_160500_create_employee_contracts_table` | `employee_contracts` (1:N) — type, start/end date, comment, status (`active`/`expired`/`terminated`) |
| `2026_05_26_160600_add_photo_to_employees` | `employees.photo_path` (nullable string — object key, never raw bytes) |

```
employees
├─ employee_addresses[3]      (type: current / permanent / emergency)
├─ employee_spouses           (1:1)
├─ employee_emergency_contacts(1:1)
├─ employee_educations[]
├─ employee_contracts[]       (most recent ACTIVE exposed as activeContract)
└─ photo_path → MinIO/S3 object key
```

## 4. Request shape

```http
POST /api/hrm/employees HTTP/1.1
tenant: acme
Authorization: Bearer <token>
Content-Type: application/json

{
  "first_name":      "Sopheak",
  "last_name":       "Mey",
  "first_name_kh":   "សុភ័ក្ត្រ",
  "last_name_kh":    "ម៉ី",
  "email":           "sopheak.mey@acme.kh",
  "phone":           "+85577000000",
  "office_phone":    null,
  "contact_phone":   null,
  "date_of_birth":   "1995-04-12",
  "gender":          "male",
  "nationality":     "Khmer",
  "nssf_id":         "12345678",
  "role_name":       "Backend engineer",
  "bank_account":    "0001-23-456789",

  "identification_type": "national_id",
  "id_card_number":      "010202020012",
  "id_issued_date":      "2018-03-04",
  "id_issued_by":        "MoI",
  "id_issued_place":     "Phnom Penh",

  "religion":       "buddhism",
  "marital_status": "single",
  "blood_group":    "O+",
  "children_count": 0,

  "department_id":   "<dept uuid>",
  "position_id":     "<position uuid>",
  "hire_date":       "2026-05-26",
  "employment_type": "full_time",
  "base_salary":     1200,
  "country":         "Cambodia",

  "photo_temp_key":  "uploads/abc123…xyz.jpg",

  "current_address": {
    "home_number":   "12B",
    "street":        "St. 178",
    "province_code": "12",
    "district_code": "1201",
    "commune_code":  "120101",
    "village_code":  "12010101",
    "group":         null,
    "lat":           11.5564,
    "lng":           104.9282
  },
  "permanent_address":  { "...": "same shape; backend skips the row when all fields empty" },
  "emergency_address":  { "...": "same shape; stored as type='emergency' in employee_addresses" },

  "spouse": {
    "name":          null,
    "date_of_birth": null,
    "education":     null,
    "occupation":    null
  },

  "emergency_contact": {
    "father_name":       "Mey Sokun",
    "father_occupation": "Farmer",
    "mother_name":       "Sok Channary",
    "mother_occupation": "Teacher",
    "phone_number":      "+85512345678",
    "home_phone":        null
  },

  "educations": [
    {
      "level":             "bachelor",
      "major_subject":     "Computer Science",
      "status":            "completed",
      "university_school": "RUPP"
    }
  ],

  "contract": {
    "type":       "udc",
    "start_date": "2026-05-26",
    "end_date":   "2027-05-26",
    "comment":    null
  }
}
```

The backend ([`EmployeeController::rules()`](../backend/app/Tenants/Modules/HRM/Controllers/EmployeeController.php)) validates each dotted-path key and ([`EmployeeService::create`](../backend/app/Tenants/Modules/HRM/Services/EmployeeService.php)) carves off each nested block, creating the employee + related rows in a single `DB::transaction`. Empty sub-blocks (e.g. blank `spouse`) skip the insert — no placeholder rows.

## 5. Photo upload (MinIO/S3 presigned PUT)

```
1. Wizard: POST /api/uploads/employee-photo  { mime, size }
                                              → { upload_url, key, ... }
2. Wizard: PUT  upload_url  (Content-Type, body=File)
                                              → 200 OK
3. Wizard: POST /api/hrm/employees            { ..., photo_temp_key: key }
                                              → EmployeeService commits the object after the DB transaction
                                              → employees.photo_path = "tenants/acme/employees/{uuid}/photo.jpg"
4. Wizard: navigateTo('/hrm/employees')
5. Listing/show: photo_url (presigned GET, 5-min TTL) appears in the JSON via the model accessor
```

Full architecture: [docs/object-storage.md](./object-storage.md).

## 6. Server-error UX

When Laravel returns 422 with field-level errors, the wizard's submit handler maps each error key back to its wizard step via `SERVER_FIELD_TO_WIZARD` and jumps to the earliest step containing a broken field. Dotted keys (e.g. `current_address.village_code`) route to step 2 anchors (`village_id`).

```ts
// excerpt: frontend/pages/hrm/employees/create.vue
const SERVER_FIELD_TO_WIZARD: Record<string, string> = {
  hire_date: 'joined_date',
  base_salary: 'salary',
  'current_address.village_code': 'village_id',
  'permanent_address.province_code': 'perm_province_id',
  'contract.start_date': 'contract_start',
  // ...
}
```

A toast surfaces the first validation message; subsequent edits re-trigger client-side Zod validation so the user can fix things without leaving the step.

## 7. Bring it up on a fresh machine

```bash
docker compose down
docker compose build app                                # picks up flysystem-aws-s3-v3
docker compose up -d                                    # MinIO + bucket bootstrap run automatically
docker compose exec app composer install
docker compose exec app php artisan tenants:migrate     # applies the 7 wizard migrations
cd frontend && npm install && npm run dev
# Open http://localhost:3000/hrm/employees/create
```

## 8. Outstanding follow-ups

- **Detail page** ([`[id]/index.vue`](../frontend/pages/hrm/employees/[id]/index.vue)) doesn't yet render the new sections — it only shows notes, documents, and leave balances. Wiring tabs for the wizard data is a follow-up.
- **Pest tests** — P0 tenancy isolation + transaction rollback on partial failure are still outstanding.
- **Per-permission policy gating** — HRM routes use only `auth:api`; `hrm.employee.write` etc. are seeded but not enforced as `can:` middleware.
- **Thumbnail pipeline** — photos are stored at upload size. A queued `intervention/image` job that writes `/photo-200.jpg` and `/photo-800.jpg` would let the listing page load smaller blobs.
- **Cross-tenant copy abuse via stolen `photo_temp_key`** — currently blocked at the prefix level (must start with `uploads/`). Adding a session-scoped token to the upload presign would tighten this further if the threat model demands it.

## 9. Cross-references

- [docs/object-storage.md](./object-storage.md) — MinIO/S3 architecture, dev/prod swap, security model.
- [docs/api-authentication.md](./api-authentication.md) — tenant + auth headers (every HRM call needs them).
- [.task/hrm/task.md](../.task/hrm/task.md) — session-level changelog with file-by-file rationale.
- [.task/geo/task.md](../.task/geo/task.md) — Cambodia geography import pipeline.
