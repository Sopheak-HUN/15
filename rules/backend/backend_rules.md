# Backend Coding Rules & Standards

## 1. General Principles
- **Clean Code**: Follow SOLID principles.
- **PSR Compliance**: Adhere to PSR-12 coding standards.
- **Strict Typing**: Use PHP 8.3 type hinting for all method parameters and return types.

## 2. Naming Conventions
- **Classes**: `PascalCase` (e.g., `AccountingService`).
- **Methods**: `camelCase` (e.g., `createJournalEntry`).
- **Variables**: `camelCase` (e.g., `invoiceData`).
- **Database Tables/Columns**: `snake_case` (e.g., `tenant_id`, `journal_entries`).
- **Boolean Variables**: Prefix with `is`, `has`, or `was` (e.g., `is_active`).

## 3. Controller Rules
- **Thin Controllers**: Controllers must only handle routing, request validation, and calling services.
- **Resourceful**: Favor resourceful controllers (`index`, `store`, `show`, `update`, `destroy`).
- **No Business Logic**: Do NOT perform calculations or complex database queries in controllers.
- **Responses**: Always return `JsonResponse` via Laravel API Resources.

## 4. Service Layer (The "Brain")
- Every module must have a `Services` directory.
- **Dependency Injection**: Services must be injected via constructor injection. No static calls for business logic.
- **Atomicity**: Ensure service methods are atomic. Use `DB::transaction()` for operations spanning multiple tables to ensure data integrity.
- **Exceptions**: Throw specific business exceptions (e.g., `InsufficientStockException`) for rule violations.
- **Early Returns**: Use guard clauses to handle edge cases and validation failures early in the method.

## 5. Model & Database Rules
- **UUIDs**: All primary keys must be UUIDs.
- **Soft Deletes**: Use the `SoftDeletes` trait for all business records.
- **Mass Assignment**: Use `$fillable` (whitelist).
- **Accessors/Mutators**: Use the new PHP 8.x `Attribute` syntax for data manipulation.
- **Global Scopes**: All tenant models MUST use the `BelongsToTenant` trait provided by the tenancy package.
- **Money**: Use `decimal(19,4)` for all currency-related columns.

## 6. Request Validation
- Always use **Form Requests** (`php artisan make:request`).
- Validation rules should be strict (e.g., use `exists:tenant_db.table,id`).

## 7. Security & Tenancy
- **No Raw Queries**: Avoid `DB::select` or `whereRaw` unless absolutely necessary to prevent SQL injection.
- **Tenant Isolation**: Never use `config()` or `env()` for tenant-specific data; use the `Tenant` model or scoped cache.
- **SSO**: Implement OAuth2/OpenID Connect using Laravel Passport for enterprise integration.

## 8. API Design
- **Key Case**: JSON responses must use `camelCase` keys.
- **Error Format**:
  ```json
  {
    "message": "Human readable error",
    "errors": { "field_name": ["Specific error message"] }
  }
  ```
- **Paginated List Response Format**:
  All list/index API endpoints returning paginated data must follow this standard response structure:
  ```json
  {
      "data": [],
      "pagination": {
          "page": 1,
          "limit": 10,
          "total": 14,
          "totalPages": 2
      }
  }
  ```
- **Versioning**: Prefix all API routes with `/v1/`.

## 9. Testing
- **Pest PHP**: Prefer Pest for readable, declarative tests.
- **Coverage**: Minimum 80% coverage for Service classes.
- **Mocking**: Mock external APIs (Payment Gateways, SMS) in tests.

## 10. Documentation
- **Service Logic**: Use JSDoc-style comments for complex business logic explanations within service methods.

## 11. Environment & Initialization Pitfalls
- **First Build `.env` Generation**: When initializing the project for the first time, you must always copy `.env.example` to `.env` and run `php artisan key:generate` to bootstrap the environment.
- **Missing `artisan` File**: Always ensure the `artisan` binary exists at the root of the backend folder. If missing, `composer install` and `composer dump-autoload` will fail during `postAutoloadDump`.
- **Composer OpenSSL Errors**: On local setups (especially Windows), ensure `extension=openssl` is uncommented in `php.ini`. Avoid disabling TLS natively in Composer.
- **Tenant Routing Initialization**: Laravel 11 requires manual registration of the `tenant.php` routing file within `bootstrap/app.php` using the `then:` closure under `withRouting()`.
- **Docker to Host Synchronization**: The `vendor/` folder must exist on the host machine for IDE code intelligence to function properly. Ensure volume binds or local `composer install` are handled correctly after Docker builds.
- **Missing Base Controller**: If you encounter `Class "App\Http\Controllers\Controller" not found`, verify that the abstract base `Controller.php` exists in `app/Http/Controllers/`. Laravel 11 uses a minimalist abstract class for this by default.
- **Tenancy Central Connection Error**: If `stancl/tenancy` throws `Database connection [central] not configured`, update `config/tenancy.php` to map `'central_connection' => env('DB_CONNECTION', 'sqlite')` instead of hardcoding `'central'`.
- **Tenancy Database Manager Namespace Error**: If your IDE or runtime complains about `Unknown class: Stancl\Tenancy\Database\TenantDatabaseManagers\...`, manually edit `config/tenancy.php` and remove the `\Database` part from the namespace (it should be `Stancl\Tenancy\TenantDatabaseManagers\...`).
- **Required PHP Extensions Missing**: If running migrations locally on Windows and you see `could not find driver (Connection: pgsql)` or `Call to undefined function mb_split()`, you must uncomment `extension=pdo_pgsql`, `extension=pgsql`, and `extension=mbstring` in your `php.ini` file.
- **`.env` Database Misconfiguration**: Never set `DB_CONNECTION=central` in your `.env`. It must be set to the actual driver. You MUST use the following block exactly when initializing the `.env` database configuration to match the Docker container:
  ```ini
  # Central Database
  DB_CONNECTION=pgsql
  DB_HOST=127.0.0.1
  DB_PORT=5433
  DB_DATABASE=erp_system
  DB_USERNAME=erp_user
  DB_PASSWORD=erp_secret
  ```
