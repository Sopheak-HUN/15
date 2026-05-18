---
name: agent-behavior-standards
description: Define how AI agents and human contributors interact with the codebase, document changes, and follow coding conventions.
---
# Agent Behavior Standards

Use this skill to define how AI agents and human contributors should interact with the codebase, document changes, and follow coding conventions. It ensures consistency, security, and traceability across all development cycles within the Enterprise ERP.

## Workflows
1. **Feature Implementation**: Follow the sequence of Rule Review -> Service Logic -> API Resource -> P0 Testing.
2. **Security Patching**: Identify vulnerabilities, apply patches via overrides/resolutions, and verify via regression tests.
3. **Module Extension**: Bootstrap new ERP features using the `skills-cli` to ensure all mandatory standard files are present.

## Architectural Constraints
- **Backend Framework**: The project backend **MUST** be implemented using **Laravel 11+** (PHP 8.2+). Do not use or suggest any other backend framework (e.g., Express, Django, Spring) for this ERP system.
- **Frontend Framework**: The frontend must be Nuxt 3+ (Vue 3, TypeScript).

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

### 5. Task & Infrastructure Initialization
- **Task Context Storage**: Upon first analysis of a task, create a task folder for storing context and task definitions, e.g., `.task/{feature}/{task.md, context.md}`.
- **Initial Setup**: When creating the first feature, you must build the `Dockerfile` and `docker-compose.yml` if they do not already exist.

## Best Practices
- **Atomic Commits**: Group related changes (e.g., Model + Migration + Service + Test) into a single logical update.
- **Least Privilege**: When modifying IAM rules, ensure permissions are as restrictive as possible.
- **Audit Trails**: Assert that critical business actions (e.g., Payroll, Invoicing) create appropriate audit log entries.

## Troubleshooting
- **CI/CD Failures**: If automated tests fail, prioritize fixing Tenancy Isolation (P0) before addressing UI/UX (P2) issues.
- **Version Mismatch**: Always use `package.json` as the source of truth for the current project version.
