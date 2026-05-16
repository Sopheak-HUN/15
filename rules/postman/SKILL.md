# Skill: Postman Collection Management

## Context
Use this skill when developing or modifying API endpoints to ensure documentation and testing collections are synchronized. This maintains a reliable testing environment for multi-tenant interactions and clear handoffs for frontend developers.

## Guidelines

### 1. Mandatory Synchronization
- **Consistency**: Every feature update MUST be accompanied by a Postman collection update.
- **Export Path**: Store the latest collection JSON in `doc/collections/`.

### 2. Multi-Tenant Scoping
- **Tenant Header**: All requests must include the `tenant: {{tenant_id}}` header.
- **Environment**: Use `{{base_url}}` for endpoints to allow switching between environments (Local, Staging).

### 3. Automation Scripts
- **Token Capture**: Login requests must include a test script to store the bearer token:
  ```javascript
  if (pm.response.code === 200) {
      pm.environment.set("token", pm.response.json().token);
  }
  ```
- **ID Capture**: Creation requests (`POST`) should capture the returned ID for subsequent `GET` or `DELETE` requests.

### 4. Payload Standards
- **Format**: JSON body only.
- **Casing**: Strict `camelCase` for all keys.
- **Descriptions**: Documentation for every path variable and query parameter is required.

## Best Practices
- **Saved Examples**: Include at least one `200 OK` response example for every request.
- **Folder Organization**: Group requests by domain module (Accounting, Sales, HR) to match the Laravel modular structure.
- **Status Codes**: Verify that requests return appropriate HTTP status codes (201 for Created, 204 for No Content, etc.).

## Troubleshooting
- **401 Unauthorized**: Ensure the `token` variable is populated and the `Authorization: Bearer {{token}}` header is active.
- **Tenant Mismatch**: If you see data from the wrong database, check that the `tenant` header value is correct.
- **Variable Not Found**: Ensure you have selected the correct Postman Environment (e.g., "ERP-Local").
