# Document Management Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `documents`
- **Actions**: `read`, `write`, `delete`, `manage`

### Feature Matrix:
| Feature | Read | Write | Delete | Manage |
|---------|------|-------|--------|--------|
| `storage` | `documents.storage.read` | `documents.storage.write` | `documents.storage.delete` | - |
| `versioning` | `documents.versioning.read`| - | - | `documents.versioning.manage`|
| `workflows` | `documents.workflows.read` | `documents.workflows.write` | `documents.workflows.delete` | - |

## 2. Implementation Standards

### Versioning Workflow
1. **Checkout**: Lock document to prevent concurrent edits.
2. **Update**: Upload new content and verify integrity.
3. **Check-in**: Release lock and finalize version.
4. **Traceability**: Link version to user and timestamp.
5. **Retrieval**: Support browsing and historical version access.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Documents`
- **Versions**: Maintain a `document_versions` table for history.
- **Storage**: Use S3 or local `tenant_path()` based on configuration.
- **Service Layer**: `Services/DocumentService.php`.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/documents/`
- **Uploads**: Drag-and-drop file uploaders with progress bars.
- **Version UI**: Diff view or version history list for documents.
