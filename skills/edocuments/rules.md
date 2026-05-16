# eDocuments (Explorer) Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `edocs`
- **Actions**: `read`, `write`, `delete`, `share`

### Feature Matrix:
| Feature | Read | Write | Delete | Share |
|---------|------|-------|--------|-------|
| `policies` | `edocs.policies.read` | `edocs.policies.write` | `edocs.policies.delete` | `edocs.policies.share` |
| `explorer` | `edocs.explorer.read` | `edocs.explorer.write` | `edocs.explorer.delete` | `edocs.explorer.share` |
| `search` | `edocs.search.read` | - | - | - |

## 2. Implementation Standards

### Document Management Flow
1. **Ingestion**: Upload file to tenant-isolated path.
2. **Metadata**: Extract tags and indexing data.
3. **Indexing**: Process for full-text search.
4. **Discovery**: Enable browsing and metadata-based filtering.
5. **Action**: View, Download, or Generate secure sharing links.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\EDocuments`
- **Storage**: Use `tenant_path()` for isolated file storage.
- **Search**: Implement full-text search indexing for document contents.
- **Security**: Public links MUST have an expiration date and optional password.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/edocuments/`
- **UI**: File tree/explorer view using PrimeVue Tree and Breadcrumb.
- **Preview**: Integrated PDF/Image previewer within the dashboard.
