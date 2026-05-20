---
name: file-uploads
description: Define secure, multi-tenant compliant protocols for file validation, chunking, storage, and retrieval.
---
# Skill: File Uploading & Storage Management

## Context
Use this skill when developing or refactoring features that involve uploading, storing, processing, retrieving, or downloading files (documents, images, media, spreadsheets, etc.) within the multi-tenant ERP ecosystem. This ensures strict tenant data isolation, robust backend security, optimal network performance, and precise file auditing.

## Guidelines

### 1. Frontend File Validation & UX
- **Pre-upload Validation**: Always validate file size, extension, and MIME type on the client side before initiating an upload to reduce unnecessary server load.
- **Visual Feedback**: Implement clear visual indicators for uploading states, including progress bars, speed, time remaining, and support for canceling/aborting active uploads.
- **Drag-and-Drop & Batching**: Support standard drag-and-drop areas using PrimeVue components (e.g., `<FileUpload>`), and batch multiple files to reduce the number of HTTP requests where applicable.

### 2. Chunked Upload Strategy
- **Large Files (P0/P1)**: Any file upload exceeding **10MB** MUST be split into chunks (e.g., 2MB-5MB per chunk) to avoid hitting server memory limits or script timeout thresholds.
- **Reassembly**: The backend must receive chunks sequentially, verify each chunk's integrity (checksum), and reassemble them only when all chunks have arrived.
- **Orphan Cleanup**: Implement a scheduled background worker to clean up incomplete or orphaned chunks older than 24 hours in the temporary storage path.

### 3. Backend Validation & File Security
- **MIME Type Verification**: Never trust the client-provided file extension or MIME type. Always run server-side verification using PHP's `fileinfo` extension to analyze the file's binary magic bytes.
- **Banned Extensions**: Strictly block executable or script-based extensions (e.g., `.php`, `.phtml`, `.py`, `.sh`, `.exe`, `.js`, `.bat`) to prevent Remote Code Execution (RCE) vulnerabilities.
- **Sanitized Filenames**: Cleanse filenames of special characters, spaces, and path traversal sequences (e.g., `../`). Generate a unique filename using a UUID combined with the original sanitized extension to prevent overwriting existing files.

### 4. Multi-Tenant Storage Isolation (P0)
- **Automatic Tenant Scoping**: Rely on Stancl Tenancy's `FilesystemTenancyBootstrapper` to dynamically suffix and re-root active disks (e.g., `local`, `public`). Avoid manually prefixing paths with tenant identifiers (such as `tenants/{id}/`) in the service logic.
- **System-Dependent Paths**: The subfolder path where uploads are saved must be dynamic and follow the system/module and feature hierarchy: `<module>/<feature>/<sub_feature_or_category>/`. For example, a recruitment resume should be stored at `hrm/recruitment/application/resume/{uuid}.ext` (mapped to `storage/app/hrm/recruitment/application/resume/{uuid}.ext`).
- **Driver Decoupling**: Support multiple Laravel Flysystem storage drivers (local, S3, MinIO) configured dynamically via tenant settings.
- **Signed Private URLs**: Do not expose direct URLs to private client documents (e.g., payslips, contracts, ID documents). Instead, generate short-lived, cryptographically signed URLs (e.g., 15-minute expiration) that are validated via tenant-scoped auth middleware.

### 5. Database Tracking & Metadata
- **Audit Trails**: Store file metadata in an `attachments` or `media` table. The schema must record:
  - `id` (UUID primary key)
  - `tenant_id` (foreign key)
  - `user_id` (uploader identifier)
  - `file_name` (sanitized original name)
  - `file_path` (relative storage path)
  - `file_size` (in bytes)
  - `mime_type` (verified MIME type)
  - `checksum` (SHA-256 hash of the file content)
  - `is_public` (boolean status flag)
- **Soft Deletes**: Apply soft deletes to file records, keeping the physical file intact for a grace period (e.g., 30 days) in case of accidental deletions.

## Best Practices
- **Integrity Checks**: Verify the file's SHA-256 hash on both the frontend and backend to guarantee the file was not corrupted during transmission.
- **Queue File Processing**: Offload heavy post-upload processes (e.g., OCR extraction, image optimization, PDF thumbnail generation, video transcoding) to the background queue (`php artisan queue:work`).
- **Antivirus Scanning**: For public or highly critical portals (e.g., recruitment application attachments), integrate a file scanner (such as ClamAV) to scan uploads prior to saving them to the permanent storage disk.

## Troubleshooting
- **PHP Upload Limits**: If uploads fail with a generic server error, ensure the following properties in `php.ini` allow for the target file sizes:
  ```ini
  upload_max_filesize = 100M
  post_max_size = 105M
  memory_limit = 256M
  ```
- **Nginx Entity Too Large**: If the server returns `413 Request Entity Too Large`, update the Nginx configuration block:
  ```nginx
  client_max_body_size 105M;
  ```
- **Expired URL Access**: If users receive `403 Access Denied` on signed files, check if system times between the backend application and the storage provider are out of sync, or if the signature expiration window is too short.
