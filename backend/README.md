# Enterprise ERP Backend

This is the multi-tenant backend for the Enterprise ERP system, built with Laravel 11. It leverages strict database isolation to serve multiple tenants securely via `stancl/tenancy`.

## 🚀 Tech Stack
- **Framework**: Laravel 11
- **Multi-Tenancy**: `stancl/tenancy` (Multi-Database Approach)
- **Authentication**: Laravel Passport (OAuth2, Tenant-Scoped)
- **Database**: PostgreSQL
- **Testing**: Pest PHP (P0 Isolation Focus)

## 📁 Architecture
The project follows a Domain-Driven Design (DDD) approach. Business logic is separated into specific modules under `app/Tenants/Modules/`. 

### Identity & Access Management (IAM)
The IAM module handles multi-tenant onboarding, role-based access control (RBAC), and compliance logging.
- **Models**: Located in `app/Tenants/Modules/IAM/Models` (`Role`, `Permission`, `AuditLog`).
- **Traits**: The `Auditable` trait automatically tracks model changes and logs them securely into the tenant's `audit_logs` table.
- **Routing**: Tenant-specific APIs are defined in `routes/tenant.php`, while central landlord APIs are in `routes/api.php`.

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
