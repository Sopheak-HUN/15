# Enterprise ERP Backend

This is the multi-tenant backend for the Enterprise ERP system, built with Laravel 11. It leverages strict database isolation to serve multiple tenants securely via `stancl/tenancy`.

## 🚀 Tech Stack
- **Framework**: Laravel 11
- **Multi-Tenancy**: `stancl/tenancy` (Multi-Database Approach)
- **Authentication**: Laravel Passport (OAuth2, Tenant-Scoped)
- **Database**: PostgreSQL
- **Object Storage**: S3-compatible — MinIO in dev, AWS S3 / Cloudflare R2 in prod. Presigned-URL uploads. See [`../docs/object-storage.md`](../docs/object-storage.md).
- **Testing**: Pest PHP (P0 Isolation Focus)

## 📁 Architecture
The project follows a Domain-Driven Design (DDD) approach. Business logic is separated into specific modules under `app/Tenants/Modules/`. 

### Identity & Access Management (IAM)
The IAM module handles multi-tenant onboarding, role-based access control (RBAC), and compliance logging.
- **Models**: Located in `app/Tenants/Modules/IAM/Models` (`Role`, `Permission`, `AuditLog`).
- **Traits**: The `Auditable` trait automatically tracks model changes and logs them securely into the tenant's `audit_logs` table.
- **Routing**: Tenant-specific APIs are defined in `routes/tenant.php`, while central landlord APIs are in `routes/api.php`.

### Human Resource Management (HRM)
The HRM module ships Workforce, Recruitment, Leave, Payroll, Performance, Suggestions, and Notes/Documents. The employee record is shaped for Cambodian HR conventions: alongside the flat `employees` table, related tables capture structured addresses, spouse, emergency contact, education history, and contracts.

| Table | Cardinality | Purpose |
|---|---|---|
| `employees` | base | Identity + employment. Encrypted columns: `national_id`, `id_card_number`, `bank_account`, `tax_id`, `base_salary` |
| `employee_addresses` | 1:N | `type ∈ {current, permanent, emergency}` — stores raw MEF province/district/commune/village codes + lat/lng |
| `employee_spouses` | 1:1 | Marriage record (name, DOB, education, occupation) |
| `employee_emergency_contacts` | 1:1 | Parents + phone; the address lives in `employee_addresses` with `type='emergency'` |
| `employee_educations` | 1:N | Degree history (level, major, status, school) |
| `employee_contracts` | 1:N | Contract history with active/expired/terminated lifecycle |

Photos live in object storage (MinIO/S3) — only the `employees.photo_path` key is in Postgres. See [`../docs/hrm-employee-creation.md`](../docs/hrm-employee-creation.md) for the full create-employee wizard ↔ backend wiring.

### Cambodia Administrative Geography
`provinces`, `districts`, `communes`, `villages` live in the **central** (landlord) database because they're shared across tenants. The `GeoController` exposes them via `GET /api/geo/{level}` (filterable by parent code) and the `geo:import` artisan command syncs from the MEF open-data portal. See [`../.task/geo/task.md`](../.task/geo/task.md).

## ⚙️ Setup Instructions

### 1. Environment Configuration
Ensure your PostgreSQL database is running. Update `.env` with your DB credentials:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=erp_system
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

Object storage (MinIO in dev, swap to AWS S3 / R2 / Spaces in prod):
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=erp-dev-root
AWS_SECRET_ACCESS_KEY=erp-dev-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=erp-uploads
AWS_ENDPOINT=http://minio:9000          # Laravel ↔ MinIO (in-cluster)
AWS_PUBLIC_ENDPOINT=http://localhost:9000  # Browser ↔ MinIO (presigned URLs)
AWS_USE_PATH_STYLE_ENDPOINT=true
```
The two-endpoint split is required because the browser can't resolve the in-cluster hostname `minio`. See [`../docs/object-storage.md`](../docs/object-storage.md).

### 2. Central Database Initialization
Run the landlord migrations to set up the core `tenants` and `domains` tables.
```bash
php artisan migrate
```

### 3. Creating a Tenant
The easiest way to provision a new tenant is by calling the central onboarding endpoint via the Postman collection. 
This will trigger the `TenantOnboardingService`, which creates the tenant record, links the subdomain, provisions a new PostgreSQL schema/database, and runs the tenant migrations automatically.

To run migrations manually for all tenants:
```bash
php artisan tenants:migrate
```

### 4. Running the Server
```bash
php artisan serve
```

## 🧪 Testing
The system uses Pest PHP for testing. It is crucial to verify that cross-tenant access is strictly denied (P0 isolation).
```bash
vendor/bin/pest
```

## 📚 API Documentation
All available API endpoints are documented in the unified Postman collection located at `../docs/postman/erp_collection.json`. 

**Key Features of the Collection**:
- Tenant-scoped URLs (`{{tenant_url}}`) and dynamic `tenant: {{tenant_id}}` HTTP headers.
- Pre-request scripts that inject environment variables seamlessly.
- Folders broken down by Module (e.g., Central Onboarding, Tenant Login, IAM Roles & Permissions, Branding, Audit Logs).

## ⚠️ Known Gotchas

- **After changing `composer.json`/`composer.lock`** — rebuild the image **and** sync the running container's vendor volume:
  ```bash
  docker compose build app
  docker compose up -d
  docker compose exec app composer install --no-scripts
  docker compose exec app php artisan optimize:clear
  ```
  The `erp_vendor` named volume is mounted on top of `/var/www/vendor` so the image's baked-in vendor is masked by whatever the volume holds. The second `composer install` runs inside the container against that volume.

## 📑 Other Docs

- [API authentication & tenant identification](../docs/api-authentication.md)
- [Object storage (MinIO/S3) architecture & presigned-URL flow](../docs/object-storage.md)
- [HRM employee creation wizard ↔ backend integration](../docs/hrm-employee-creation.md)
