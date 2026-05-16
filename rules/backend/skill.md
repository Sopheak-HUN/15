# Skill: Backend API & Business Logic Implementation

## Context
Use this skill when implementing new API endpoints, business services, or database interactions in the Laravel backend. This ensures that the code follows enterprise-grade standards, maintains multi-tenant isolation, and is highly maintainable.

## Guidelines

### 1. Controller Implementation (API Surface)
- **Thin Controllers**: Limit controllers to request validation and service calling.
- **Form Requests**: Always use dedicated Form Request classes for validation.
- **Resourceful Routing**: Stick to standard CRUD methods (`index`, `store`, `show`, `update`, `destroy`).
- **Response Handling**: Always return `JsonResource` or `ResourceCollection`.

### 2. Service Layer (Business Domain)
- **Logic Isolation**: Move all business logic, calculations, and external integrations to Service classes.
- **Atomicity**: Ensure service methods are atomic. Use `DB::transaction()` for operations spanning multiple tables.
- **Dependencies**: Inject models or other services via the constructor.

### 3. Model & Database Patterns
- **UUIDs**: Use UUIDs as primary keys for all models.
- **Tenancy**: Ensure the `BelongsToTenant` trait is applied to all tenant-scoped models.
- **Soft Deletes**: Apply `SoftDeletes` to preserve business audit trails.
- **Accessors/Mutators**: Use the new PHP 8.x `Attribute` syntax for model data manipulation.

### 4. API Design & Security
- **CamelCase**: Ensure API responses use `camelCase` for keys.
- **Authentication**: Use Laravel Passport for API authentication.
- **Authorization**: Use Policies (`php artisan make:policy`) to authorize actions based on tenant permissions.

## Best Practices
- **Strict Typing**: Leverage PHP 8.3 features like constructor property promotion and strict return types.
- **Custom Exceptions**: Throw domain-specific exceptions (e.g., `PaymentFailedException`) instead of generic ones.
- **Early Returns**: Use guard clauses to handle edge cases early in your methods.
- **Documentation**: Use JSDoc-style comments for complex business logic explanations within services.

## Troubleshooting
- **Tenant Scope Missing**: If data from other tenants appears, verify the `BelongsToTenant` trait and check if the `tenant_id` is set correctly in the session.
- **N+1 Queries**: Use `Eager Loading` (`with()`) to prevent performance bottlenecks. Use the `laravel-query-detector` in development.
- **Validation Errors**: If 422 errors are unclear, ensure the Form Request's `messages()` method provides helpful feedback.
- **Transaction Deadlocks**: Keep database transactions as short as possible and avoid external API calls inside them.
