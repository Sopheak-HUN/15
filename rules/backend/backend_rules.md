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
- **Versioning**: Prefix all API routes with `/v1/`.

## 9. Testing
- **Pest PHP**: Prefer Pest for readable, declarative tests.
- **Coverage**: Minimum 80% coverage for Service classes.
- **Mocking**: Mock external APIs (Payment Gateways, SMS) in tests.

## 10. Documentation
- **Service Logic**: Use JSDoc-style comments for complex business logic explanations within service methods.
