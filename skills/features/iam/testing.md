# Testing Strategy: IAM & Security

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Tenant A users/roles MUST be invisible to Tenant B requests. |
| **P0** | **Authentication** | Valid credentials must return JWT; invalid must return 401. |
| **P1** | **API Contract** | Postman variables `{{token}}` and `{{tenant_id}}` must be used. |
| **P1** | **RBAC** | Permission keys (e.g. `iam.users.write`) must be strictly enforced. |
| **P2** | **Audit Trail** | All role/permission changes must be logged in `audit_logs`. |

## 2. Backend Testing (Pest PHP)

### Tenancy Isolation (P0)
- **Rule**: Users from Tenant A must never be able to authenticate or view users from Tenant B.
- **Test Case**: `it('blocks cross-tenant user access', ...)` using `actingAs()` within a scoped tenant context.

### Permission Enforcement
- **Rule**: Only users with `iam.roles.manage` can create new roles.
- **Test Case**: Assert `403 Forbidden` for a standard `Employee` trying to access the Role Management API.

### Authentication Flow
- **Rule**: Valid credentials must return a Bearer Token and the correct tenant handle.
- **Validation**: Test for `422 Unprocessable Entity` on missing email or password.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Environment**: Ensure `{{tenant_id}}` matches the `tenant` header in all requests.
- **Automated Test**: The Login request must automatically save the `token` variable.

## 3. Audit Logging
- **Assertion**: Every role change or user creation must trigger an entry in the `audit_logs` table.
- **Test Case**: `assertDatabaseHas('audit_logs', ['action' => 'user_created'])`.
