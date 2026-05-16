# Skill: Postman Collection Standardization

## Context
Use this skill when designing, documenting, or testing API endpoints for the Enterprise ERP. Consistent Postman collections ensure that the API can be tested effectively in a multi-tenant environment and provide clear documentation for frontend developers.

## Guidelines

### 1. Variables and Environment
- **Base URL**: Always use the `{{base_url}}` variable for the protocol and domain.
- **Tenant Identification**: Always use the `{{tenant_id}}` variable to specify the active tenant.
- **Authentication**: Store bearer tokens in a `{{token}}` variable.

### 2. Header Standards
Every request MUST include the following headers:
- `Content-Type`: `application/json`
- `tenant`: `{{tenant_id}}` (Used by Laravel middleware to switch databases).
- `Accept`: `application/json`

### 3. Collection Structure
- **Root Level**: Named `ERP API - [Module Name]`.
- **Folders**: Organize by business domain (e.g., `Authentication`, `Sales`, `Inventory`).
- **Request Naming**: Use clear, action-oriented names (e.g., `Register User`, `Create Sales Order`).

### 4. Request Body
- **Format**: Always use `raw` JSON.
- **Casing**: Use `camelCase` for all JSON keys (e.g., `firstName`, `phoneNumber`).
- **Examples**: Provide a realistic example body for every `POST`, `PUT`, and `PATCH` request.

### 5. Automated Scripts
- **Pre-request Scripts**: Use to set up temporary data or timestamps.
- **Tests**: Implement automatic variable saving for tokens:
  ```javascript
  if (pm.response.code === 200) {
      var jsonData = pm.response.json();
      pm.environment.set("token", jsonData.token);
  }
  ```

## Best Practices
- **Descriptions**: Add a detailed description to every request explaining the parameters and possible response codes.
- **Syncing**: Keep the Postman collection synced with the `app/Tenants/Modules/*/Routes/api.php` files.
- **Isolation**: Use different Postman Environments for `Local`, `Staging`, and `Production`.

## Troubleshooting
- **403 Forbidden**: Check if the `tenant` header is present and the `{{tenant_id}}` variable is set correctly in your environment.
- **404 Not Found**: Verify that the `{{base_url}}` includes the correct API prefix (e.g., `http://localhost:8000/api`).
- **Token Expired**: Run the `Authentication/Login` request to refresh the `{{token}}` variable.
