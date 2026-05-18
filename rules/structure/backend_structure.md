# Backend Architectural Structure: Modular Laravel

## Overview
To maintain scalability in a large ERP, we use a **Modular Monolith** approach. All business logic is encapsulated within specific domain modules under `app/Modules`.

## 1. Directory Hierarchy

```text
/app
├── Console/               # Artisan commands (landlord + tenant)
├── Exceptions/            # Custom exceptions
├── Http/
│   ├── Controllers/
│   │   ├── Central/       # Landlord controllers (Tenant management, Subscriptions)
│   │   └── Tenant/        # Tenant-scoped controllers (ERP modules)
│   ├── Middleware/        # Global + tenant-specific middleware
│   └── Requests/          # Form requests
├── Models/
│   ├── Central/           # Landlord models (e.g., Tenant, Subscription)
│   ├── Tenant/            # Tenant-scoped models (e.g., Product, Invoice)
│   └── Traits/            # Shared model traits
├── Policies/              # Authorization policies
├── Providers/
│   ├── CentralServiceProvider.php   # Landlord service provider
│   ├── TenantServiceProvider.php    # Tenant service provider
│   └── ...               # Other providers (Auth, Route, etc.)
├── Services/
│   ├── Central/           # Landlord services (e.g., TenantCreator)
│   └── Tenant/            # Tenant services (e.g., AccountingService)
└── View/
    └── Components/       # Blade components

/database
├── migrations/
│   ├── central/           # Landlord migrations (tenants table, etc.)
│   └── tenant/            # Tenant migrations (ERP tables)
└── seeders/

/routes
├── central.php           # Landlord routes (tenant management)
├── tenant.php            # Tenant routes (ERP modules)
└── web.php                # Shared routes (if any)

/config
├── tenancy.php           # Tenancy configuration (stancl/tenancy)
└── ...                   # Other Laravel configs

/resources
├── views/
│   ├── central/           # Landlord views
│   └── tenant/            # Tenant views
└── lang/                 # Localization files

/modules                  # Optional: ERP modules (if using Laravel Modules)
├── Accounting/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   ├── Models/
│   ├── Services/
│   ├── Resources/        # API resources, collections
│   ├── Routes/
│   └── database/         # Module-specific migrations/seeders
├── Inventory/
└── Sales/

/tenant                   # Tenant-specific files (if not using stancl/tenancy)
└── public/               # Tenant assets (CSS, JS, images)

/shared                   # Shared utilities, interfaces, helpers
├── Contracts/            # Interfaces
├── Helpers/              # Helper functions
└── Traits/               # Shared traits

/tests
├── Feature/
│   ├── Central/
│   └── Tenant/
└── Unit/
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
