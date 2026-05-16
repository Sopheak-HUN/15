# Skill: ERP Structural Implementation (Full-Stack)

## Context
Use this skill when tasked with extending the ERP's functionality, creating new business modules, or modifying the multi-tenant architecture on both Backend (Laravel) and Frontend (NuxtJS). This ensures consistency, security, and modularity across the entire stack.

## Backend Guidelines (Laravel)

### 1. Creating a New ERP Module
When adding a new feature (e.g., `Accounting`, `Payroll`), follow this initialization pattern:
1. **Directory Setup**: Create a dedicated directory under `app/Tenants/Modules/{ModuleName}`.
2. **Standard Sub-folders**:
   - `Controllers/`: API handlers.
   - `Models/`: Eloquent entities using UUIDs and `BelongsToTenant`.
   - `Services/`: Business logic orchestration.
   - `Resources/`: API response transformers.
   - `Routes/`: `api.php` scoped with `tenancy` middleware.
   - `Database/Migrations/`: Tenant-specific schema changes.

### 2. Service Layer Implementation
- **Logic Ownership**: All database writes and business calculations MUST live in Service classes.
- **Transactions**: Wrap multi-table operations in `DB::transaction()`.
- **Exceptions**: Throw domain-specific exceptions for business rule violations.

### 3. Tenant-Aware Development
- **Global Scopes**: Ensure models use the `BelongsToTenant` trait.
- **Migration Location**: Never place tenant tables in the central `database/migrations` folder.
- **Environment**: Do not use `env()` for tenant-specific settings; use the active tenant configuration.

## Frontend Guidelines (NuxtJS & PrimeVue)

### 1. Creating a New Feature Module
When adding a new business feature (e.g., `Accounting`, `Sales`), follow this pattern:
1. **Module Directory**: Create a directory in `src/modules/{ModuleName}`.
2. **Sub-folders**:
   - `components/`: Feature-specific UI elements.
   - `pages/`: UI routes and views.
   - `store/`: Pinia state management.
   - `services/`: API client classes.
   - `utils/`: Module-specific logic.

### 2. UI Development with PrimeVue
- **Components**: Prioritize PrimeVue components over custom HTML.
- **Styling**: Use PrimeVue's `pt` (Pass Through) property for deep customization.
- **Tokens**: Use global CSS variables for colors and spacing to ensure theme compatibility.

### 3. Data Flow & State Management
- **Services**: All API calls MUST be encapsulated in service classes.
- **Pinia**: Use feature-specific stores.
- **Reactivity**: Use `ref()` and `computed()` properly within `<script setup>`.

### 4. Multi-Tenant Integration
- **Context**: Always use the `useTenant` composable to access tenant-specific branding or configurations.
- **Route Guards**: Ensure the `tenant-check` middleware is active for all protected routes.

## Best Practices
- **Type Safety**: Use TypeScript for both Backend (PHP 8.3 typing) and Frontend.
- **UUIDs**: Always use UUIDs for primary keys in tenant-facing records.
- **Thin Controllers**: Limit controllers to request validation and service calling.
- **Reusable Components**: Move generic UI patterns to `src/components/ui`.

## Troubleshooting
- **Data Leakage**: Verify the `BelongsToTenant` trait and `tenancy` middleware.
- **Theme Issues**: Check if components use hardcoded colors instead of CSS variables in Dark Mode.
- **API Failures**: Verify the `X-Tenant-ID` header is being sent in both the API client and handled in the backend.
