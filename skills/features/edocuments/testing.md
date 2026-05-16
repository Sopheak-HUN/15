# Testing Strategy: eDocuments (Explorer)

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Physical file paths must be isolated via `tenant_path()`. |
| **P0** | **Security** | Expired public links must be inaccessible (410 Gone). |
| **P1** | **Search** | Full-text search must only return results from the active tenant. |
| **P1** | **API Contract** | File upload/list endpoints must use the `tenant` header. |
| **P2** | **Preview** | Integrated previewer must not leak temp file access. |

## 2. Backend Testing (Pest PHP)

### Path Isolation (P0)
- **Rule**: Files must be strictly isolated via `tenant_path()`.
- **Test Case**: Attempt to access a file path using another tenant's handle in the URL. Assert `404`.

### Search Integrity
- **Rule**: Search results must only include files from the active tenant.
- **Test Case**: Seed files in two tenants and assert that a search in Tenant A returns 0 results from Tenant B.

### Secure Sharing
- **Rule**: Expired public links must return `410 Gone`.
- **Test Case**: Create a link with a past expiration date and attempt access.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Headers**: Verify `tenant` header is used to derive the correct storage root.

## 3. Performance
- **Assertion**: Large directory listings should use pagination.
- **Test Case**: Seed 100+ files and verify pagination metadata in response.
