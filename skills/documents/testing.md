# Testing Strategy: Document Management

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Document files and versions are isolated by `tenant_id`. |
| **P0** | **Concurrency** | Locked documents (checkout) must block other updates. |
| **P1** | **Versioning** | All document updates must create a traceable new version. |
| **P1** | **API Contract** | Follows `erp_collection.json`; uses `lastVersionId`. |
| **P2** | **Privacy** | Encrypted documents must be unreadable by unauthorized roles. |

## 2. Backend Testing (Pest PHP)

### Security (P0)
- **Rule**: Access to sensitive documents must be role-restricted.
- **Test Case**: Attempt to view an encrypted HR document with a Sales role. Assert `403`.

### Multi-Tenancy
- **Rule**: Files are stored in tenant-isolated paths.
- **Test Case**: Verify `tenant_id` is used to construct the file storage path.

### Version Integrity
- **Rule**: Checking out a document must lock it for other users.
- **Test Case**: User A checks out a doc; User B attempts checkout. Assert `409 Conflict`.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Scenarios**: Test document upload, version retrieval, and locking.

## 3. Storage
- **Assertion**: Deleting a document record must also remove the physical file (or move it to archive).
- **Test Case**: `Storage::assertMissing()` after document deletion.
