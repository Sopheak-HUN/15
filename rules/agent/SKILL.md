# Agent Behavior Standards

Use this skill to define how AI agents and human contributors should interact with the codebase, document changes, and follow coding conventions. It ensures consistency, security, and traceability across all development cycles within the Enterprise ERP.

## Guidelines

### 1. Documentation & Code Style
- **JSDoc Mandatory**: All public methods, controllers, and services MUST follow the standardized JSDoc pattern.
- **Reference**: See [Code Documentation Standards](./comments.md) for the exact pattern.

### 2. Versioning & Integrity
- **Simultaneous Updates**: Version bumps must be reflected in `package.json`, `README.md`, and `SECURITY.md` simultaneously.
- **Reference**: See [Version Control and Project Updates](./version.md) for the versioning pattern.

### 3. Testing & Isolation (P0)
- **Tenancy Validation**: Every feature must prove data isolation between tenants using the standard Pest protocol.
- **Reference**: See [Backend Feature Testing Protocol](./testing.md) for the isolation test pattern.

### 4. Structural Consistency
- **Modular Design**: Always use the standard ERP module structure (`app/Tenants/Modules/` for Backend and `src/modules/` for Frontend).
- **Service Layer**: Business logic must be encapsulated in Service classes, not in Controllers.

## Best Practices
- **Atomic Commits**: Group related changes (e.g., Model + Migration + Service + Test) into a single logical update.
- **Least Privilege**: When modifying IAM rules, ensure permissions are as restrictive as possible.
- **Audit Trails**: Assert that critical business actions (e.g., Payroll, Invoicing) create appropriate audit log entries.

## Troubleshooting
- **CI/CD Failures**: If automated tests fail, prioritize fixing Tenancy Isolation (P0) before addressing UI/UX (P2) issues.
- **Version Mismatch**: Always use `package.json` as the source of truth for the current project version.
