# eDocuments (Explorer)

Use this skill when building the centralized document repository, policy manager, or multi-system file explorer.

## Guidelines

### 1. File Organization
- **Multi-Tenant Storage**: Always use `tenant_path()` to ensure files are isolated at the OS level.
- **Metadata**: Support custom tags and categories for advanced filtering.

### 2. Search & Discovery
- **Full-Text Search**: Implement indexing for PDF and Word documents using Elasticsearch or similar.
- **Breadcrumbs**: Ensure the explorer UI provides clear navigation paths.

### 3. Sharing & Security
- **Secure Links**: Public links must be signed, expiring, and optionally password-protected.
- **Acknowledgement**: For policies, implement a "Mark as Read/Acknowledged" feature for employees.

## Best Practices
- **Lazy Loading**: Use virtual scrolling or pagination for directories with thousands of files.
- **Previews**: Provide inline previews for common file types (PDF, Images, Video) without downloading.
- **Version Control**: Maintain a history of changes for critical documents like SOPs.

## Troubleshooting
- **Upload Failed**: Check server `upload_max_filesize` and `post_max_size` settings in PHP.
- **Broken Previews**: Verify the `Storage::url()` is generating accessible links for the current tenant subdomain.
- **Search Inaccuracy**: Re-run the `DocumentIndexJob` if new files are not appearing in search results.
