# Backend Architectural Structure: Modular Laravel

## Overview
To maintain scalability in a large ERP, we use a **Modular Monolith** approach. All business logic is encapsulated within specific domain modules under `app/Modules`.

## 1. Directory Hierarchy

```text
/app
├── Central/               # Landlord logic (Tenant management, Subscriptions, Global Settings)
│   ├── Controllers/
│   ├── Models/
│   └── Services/
├── Tenants/               # Primary ERP logic (Tenant-scoped)
│   ├── Modules/           # Business domain modules
│   │   ├── Accounting/    # Example Module
│   │   │   ├── Controllers/
│   │   │   ├── Models/
│   │   │   ├── Services/
│   │   │   ├── Resources/
│   │   │   └── Routes/
│   │   ├── Inventory/
│   │   └── Sales/
│   ├── Core/              # Tenant-specific shared logic
│   │   ├── Traits/
│   │   └── Middleware/
│   └── Providers/         # Tenant-scoped service providers
└── Shared/                # Logic shared by both Central and Tenants (Interfaces, Utilities)
```

## 2. Structural Rules

### Service Layer Requirement
- **Controllers** MUST be thin. They only handle request validation and response returning.
- **Services** MUST contain all business logic. No database writes should happen directly in Controllers.
- **Example**: `AccountingService::createJournalEntry(array $data)`

### Routing Strategy
- Each module has its own `Routes/api.php`.
- These routes are automatically prefix-scoped in the `ModuleServiceProvider`.
- All tenant routes MUST be wrapped in the `tenancy` middleware.

### Database Migrations
- **Central Migrations**: Stored in `database/migrations/central`. Used for system-level tables.
- **Tenant Migrations**: Stored in `app/Modules/*/Database/Migrations`. These are executed by the `tenants:migrate` command.

### Namespace Convention
- Use `App\Modules\{ModuleName}` as the base namespace.
- Example: `App\Modules\Accounting\Models\JournalEntry`.

## 3. Communication between Modules
- Modules should interact via **Interfaces** or **Internal APIs**.
- Avoid direct database joins between unrelated modules (e.g., Accounting should not join directly to HR unless through a shared bridge).
- Use **Events/Listeners** for cross-module side effects (e.g., `OrderConfirmed` in Sales triggers `StockReserved` in Inventory).

## 4. Automation
- Create a `make:module` command to scaffold the standard directory structure for new ERP features.
