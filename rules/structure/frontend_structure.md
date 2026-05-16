# Frontend Architectural Structure: Modular NuxtJS & PrimeVue

## Overview
To support a complex multi-tenant ERP, the frontend uses a **Domain-Driven Module** structure. This ensures that features like "Accounting" or "Inventory" are self-contained and easy to maintain or toggle.

## 1. Directory Hierarchy

```text
/src
├── assets/            # Global styles (SCSS), images, fonts
├── components/        # Shared UI Components (Generic)
│   ├── ui/            # PrimeVue overrides & custom base components
│   ├── common/        # Shared business components (e.g., TenantSelector)
│   └── layout/        # Shared layout parts (Sidebar, Header)
├── composables/       # Global hooks (useAuth, useApi, useTenant)
├── constants/         # Enums and static configuration
├── layouts/           # Page Layouts (default, auth, print, dashboard)
├── middleware/        # Route guards (auth, guest, rbac, tenant-check)
├── modules/           # Domain-Specific Feature Modules
│   ├── accounting/    # Example Module
│   │   ├── components/ # Module-specific components
│   │   ├── pages/      # Module-specific routes
│   │   ├── store/      # Module-specific Pinia state
│   │   ├── services/   # Module-specific API calls
│   │   └── utils/      # Module-specific helpers
│   ├── inventory/
│   └── sales/
├── pages/             # Root & Fallback pages (Home, Login, Error)
├── plugins/           # External libraries (PrimeVue, i18n, Toast)
├── services/          # Base API client (Axios/Fetch wrapper)
├── stores/            # Global state (User Profile, UI State)
└── utils/             # Global utility functions
```

## 2. Structural Rules

### PrimeVue Implementation
- Use **PrimeVue** as the primary UI library.
- Custom styling should be handled via **CSS Variables** or scoped SCSS to maintain consistency.
- Avoid inline styles. Use PrimeVue's `pt` (Pass Through) property for deep styling when necessary.

### Data Fetching (Services)
- **Services** MUST handle all API communication.
- Pages and Components should never call `fetch` or `useFetch` directly with raw URLs.
- **Example**: `AccountingService.getInvoices(params)`

### State Management (Pinia)
- Use **Pinia** for state management.
- Global state (User, Settings) stays in `@/stores`.
- Domain state (Current Invoice, Stock List) MUST stay in `@/modules/{name}/store`.

### Component Composition
- Components should follow the **SFC (Single File Component)** pattern with `<script setup>`.
- Use **TypeScript** for all components to ensure type safety across the ERP.

## 3. Modular Routing
- Use Nuxt's `pages` directory for core routing.
- For modules, routes can be dynamically registered or placed in `pages/{module-name}` to keep the structure clean.

## 4. Multi-Tenant Considerations
- The `useTenant` composable must be used to inject the current tenant's context (branding, permissions).
- API headers must automatically include the `X-Tenant-ID` or be handled via the tenant-specific subdomain.

## 5. Design System
- All colors, spacing, and typography must be derived from the core design tokens defined in `assets/css/main.css`.
- Support for **Dark Mode** must be handled using PrimeVue's built-in switching capabilities.
