# Feature: Governance & Security (IAM)

## Overview
The Identity & Access Management (IAM) module is the core security layer of the ERP. it handles multi-tenancy, user authentication, and granular role-based access control (RBAC).

## 1. Multi-Tenancy
- **Tenant Isolation**: Every request must be scoped to a `tenant_id`.
- **Database Strategy**: Multi-database isolation using `stancl/tenancy`.
- **Branding**: Tenant-specific logos, primary colors, and subdomains (e.g., `company.erp.app`).

## 2. Roles & Permissions (RBAC)

### Role Management
- Roles are defined at the Tenant level.
- **System Roles** (Default):
    - `Super Admin`: Full system access across all modules.
    - `HR Manager`: Access to HCM modules and payroll execution.
    - `Finance Manager`: Access to accounting and financial reporting.
    - `Employee`: Access to self-service portals (Leave, Profile, Payslips).
- **Custom Roles**: Tenants can create custom roles with specific permission sets.

### Permission Mapping
- Permissions are granular and follow the pattern `module.feature.action`.
- **Example Permissions**:
    - `hcm.employee.view`: Can view employee directory.
    - `hcm.payroll.execute`: Can process and finalize payroll.
    - `iam.roles.manage`: Can create and edit roles.
- **Permission Types**:
    - `Read`: View data.
    - `Write`: Create/Edit data.
    - `Delete`: Remove data (Soft Delete).
    - `Export`: Download sensitive data (Requires higher privilege).

## 3. Technical Implementation

### Backend (Laravel Passport)
- **Scopes**: Use Passport Scopes to enforce permissions at the API level.
- **Policies**: Every Model must have a corresponding Laravel Policy that checks `user()->hasPermission()`.
- **Middleware**: Use `EnsureTenantAccess` middleware to verify tenant context.

### Frontend (Nuxt/PrimeVue)
- **Directive**: `v-can="'permission.name'"` to conditionally show/hide UI elements.
- **Composables**: `usePermissions()` to check access in logic.
- **Dynamic Menus**: Sidebars and navigation are filtered based on the user's role permissions.

## 4. Security & Compliance
- **Audit Logs**: Every sensitive action (Role change, Permission grant, Data export) is logged with the actor's ID and timestamp.
- **OTP Verification**: Critical permission changes (e.g., assigning `Super Admin`) require a secondary OTP.
