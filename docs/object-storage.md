# Object Storage (MinIO / S3)

## TL;DR

User-uploaded blobs (employee photos today, more later) live in an
S3-compatible object store — **never** on the Laravel app's local
filesystem and **never** in git. The browser uploads bytes directly to
the bucket via a 10-minute presigned PUT URL; Laravel only issues the
URL and later moves the committed object into the tenant's permanent
prefix.

Dev uses **MinIO** running in `docker-compose.yml`. Production swaps the
endpoint to AWS S3, Cloudflare R2, or DigitalOcean Spaces with zero code
changes.

```
Browser → POST /api/uploads/employee-photo                    → Laravel signs PUT URL (10 min)
Browser → PUT  <presigned URL>           (Content-Type, File) → MinIO PutObject  uploads/{nanoid}.{ext}
Browser → POST /api/hrm/employees { photo_temp_key, ... }    → Laravel
                                                                  ├─ DB::transaction creates employee + relations
                                                                  ├─ CopyObject uploads/ → tenants/{handle}/employees/{uuid}/photo.{ext}
                                                                  ├─ DeleteObject the temp
                                                                  └─ stamp employees.photo_path
GET    /api/hrm/employees/{id}                                → JSON includes photo_url (5-min presigned GET)
<img>  → loads from MinIO directly with the presigned URL
```

---

## 1. Why two S3 endpoints

The signing endpoint stamped into the presigned URL has to be the host
the **browser** can reach. Laravel-to-MinIO traffic happens over the
Docker network with a different host. We can't use the same value:

| Env var | Used by | Value (dev) | Why |
|---|---|---|---|
| `AWS_ENDPOINT` | Laravel ↔ MinIO (copy / delete / get) | `http://minio:9000` | Docker service name; only resolves inside the `erp_network` |
| `AWS_PUBLIC_ENDPOINT` | Browser ↔ MinIO (presigned URLs) | `http://localhost:9000` | Reachable from the host machine where the browser runs |

[`App\Services\S3UploadService`](../backend/app/Services/S3UploadService.php)
instantiates a **second** `Aws\S3\S3Client` pointed at the public
endpoint and uses it ONLY for `createPresignedRequest()`. Every other
operation goes through Laravel's default `Storage::disk('s3')` which
uses `AWS_ENDPOINT`.

For AWS S3 (no MinIO), both vars can point at the same `https://s3.{region}.amazonaws.com` URL.

## 2. Bucket layout

```
erp-uploads/                            ← single bucket, all tenants share
├── uploads/                            ← short-lived scratchpad (1-day lifecycle)
│   ├── abc123…xyz.jpg                  ← presigned PUT lands here
│   └── def456…uvw.png
└── tenants/
    └── {handle}/                       ← per-tenant prefix
        └── employees/
            └── {employee_uuid}/
                └── photo.jpg           ← committed by EmployeeService
```

| Rule | Why |
|---|---|
| Temp prefix is flat (`uploads/`) | S3 lifecycle rules don't support wildcards — flat prefix lets one rule cover all tenants |
| Bucket is **private** (no public ACLs) | Reads issue short-TTL presigned GETs via the model accessor |
| Tenant prefix is `tenants/{handle}/` | Future per-tenant IAM policies can scope the prefix per role |
| Final keys include the employee UUID | Multiple employees in the same tenant don't collide; rename-safe |

## 3. Components

### 3.1 Docker services

```yaml
# docker-compose.yml
services:
  minio:                        # S3 API on :9000, console UI on :9001
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER:     erp-dev-root
      MINIO_ROOT_PASSWORD: erp-dev-secret
    healthcheck:
      test: ["CMD-SHELL", "mc ready local 2>/dev/null || curl -fsS http://localhost:9000/minio/health/live"]

  minio-init:                   # runs once: creates bucket + lifecycle rule
    image: minio/mc:latest
    entrypoint: >
      /bin/sh -c "
        mc alias set local http://minio:9000 erp-dev-root erp-dev-secret;
        mc mb --ignore-existing local/erp-uploads;
        mc ilm rule add --expire-days 1 --prefix 'uploads/' local/erp-uploads;
      "
```

`minio` is wired into `app` and `queue` via `depends_on: { condition: service_healthy }`.

### 3.2 Laravel config

`backend/config/filesystems.php` — the `s3` disk gains a `public_endpoint` field that `S3UploadService` reads.

```php
's3' => [
    'driver'                  => 's3',
    'key'                     => env('AWS_ACCESS_KEY_ID'),
    'secret'                  => env('AWS_SECRET_ACCESS_KEY'),
    'region'                  => env('AWS_DEFAULT_REGION'),
    'bucket'                  => env('AWS_BUCKET'),
    'endpoint'                => env('AWS_ENDPOINT'),           // in-cluster
    'public_endpoint'         => env('AWS_PUBLIC_ENDPOINT'),    // signing-only
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
],
```

### 3.3 S3UploadService

[`App\Services\S3UploadService`](../backend/app/Services/S3UploadService.php) is the only place that touches the bucket.

| Method | Used by | Notes |
|---|---|---|
| `signEmployeePhotoPut($mime, $maxBytes)` | `UploadController::employeePhoto` | Signs a 10-min PUT URL; returns `{ upload_url, key, mime, max_bytes, expires_in }` |
| `signGet($key, $ttl = '+5 minutes')` | `Employee::photoUrl` accessor | Issues a short-TTL GET URL for `<img>` tags |
| `commitObject($sourceKey, $finalKey)` | `EmployeeService::create` | Copies the temp blob to the final key, deletes the temp. **Refuses any source key not under `uploads/`** — prevents forged-key cross-tenant copies |
| `delete($key)` | (reserved for soft-delete cleanup) | — |

### 3.4 HTTP endpoint

| Endpoint | Auth | Description |
|---|---|---|
| `POST /api/uploads/employee-photo` | `auth:api` + tenant scope | Body: `{ mime: 'image/jpeg' \| 'image/png', size: <bytes ≤ 2 MB> }`. Returns presign payload. |

Defined in [`routes/tenant.php`](../backend/routes/tenant.php) inside the `InitializeTenancyByRequestData` middleware group, so the `tenant` header is enforced like every other API call.

### 3.5 Frontend

[`frontend/composables/useUpload.ts`](../frontend/composables/useUpload.ts) wraps the two-step dance:

```ts
const uploads = useUpload()
const key = await uploads.uploadEmployeePhoto(file)
// then POST /api/hrm/employees with { photo_temp_key: key, ... }
```

It uses the global `fetch` (not `$fetch`) for the PUT so the body stays the raw `File` — `ofetch`'s JSON encoding would corrupt the bytes.

## 4. Security model

| Layer | Guard |
|---|---|
| Presign endpoint | Behind `auth:api` + tenant middleware; mime + size validated server-side (JPEG/PNG, ≤ 2 MB) |
| Forged `photo_temp_key` | `commitObject` refuses any source key not under `uploads/`; can't trick Laravel into copying from another tenant's prefix |
| Read access | Bucket private; reads go through 5-min presigned GETs in `photo_url`. The URL leaves the response when it expires; refresh by fetching the row again |
| Abandoned uploads | 1-day MinIO lifecycle rule on `uploads/` auto-deletes orphans (browser closed, validation failed, etc.) |
| At-rest | MinIO server-side encryption (SSE-S3) can be turned on by the operator; for prod managed services, enable bucket-default SSE |

## 5. Production swap (AWS S3 / R2 / Spaces)

1. Set the four env vars on the app/queue containers:
   ```
   AWS_BUCKET=erp-uploads-prod
   AWS_ENDPOINT=https://{accountid}.r2.cloudflarestorage.com   # R2 example
   AWS_PUBLIC_ENDPOINT=https://cdn.acme-erp.com                # custom domain CNAMEd at the bucket
   AWS_USE_PATH_STYLE_ENDPOINT=false                           # virtual-hosted-style for AWS/R2
   ```
2. Use **scoped IAM credentials**, not the bucket root keys. Minimum required actions: `s3:GetObject`, `s3:PutObject`, `s3:DeleteObject`, `s3:CopyObject` (or `s3:PutObject` on the destination + `s3:GetObject` on the source). Scope the resource ARN to `bucket/uploads/*` and `bucket/tenants/*`.
3. Remove the `minio` and `minio-init` services from `docker-compose.yml`.
4. Set the same lifecycle rule on the prod bucket (`uploads/` prefix, 1-day expire) — `aws s3api put-bucket-lifecycle-configuration` or the provider's UI.
5. **R2 specifically** — set `region: auto` (R2 ignores it but the SDK still requires a value) and enable "Allow API for object access" in the dashboard.
6. **Cloudflare R2 has zero egress fees**; for an ERP where photos render on every list page, this is meaningfully cheaper than AWS S3.

## 6. Adding a new upload lane

Today only employee photos use this flow. To add (say) inventory product images:

1. Add a route + controller action that calls `S3UploadService::signEmployeePhotoPut(...)` with a different lane name and mime allowlist. Consider factoring a generic `signPut($lane, $mime)` if the count grows.
2. Define the final-key pattern in the consuming service (mirror `EmployeeService::commitEmployeePhoto`).
3. Add a `photo_path`-style column to the consuming model + an `Attribute`-cast `*_url` accessor.
4. Update the lifecycle rule if you want a longer/shorter scratchpad TTL.

No changes to MinIO / bucket layout / Docker config — everything happens in PHP.

## 7. Local diagnostics

- **MinIO console** — http://localhost:9001 (user `erp-dev-root`, pass `erp-dev-secret`). Browse the bucket, check lifecycle rules, view object headers.
- **mc CLI inside the running minio container**:
  ```bash
  docker compose exec minio mc alias set local http://minio:9000 erp-dev-root erp-dev-secret
  docker compose exec minio mc ls --recursive local/erp-uploads
  docker compose exec minio mc ilm rule ls local/erp-uploads
  ```
- **Tail Laravel logs to see presign + commit calls**:
  ```bash
  docker compose exec app tail -f storage/logs/laravel.log
  ```

## 8. Cross-references

- [docs/hrm-employee-creation.md](./hrm-employee-creation.md) — the consumer that exercises this flow end-to-end.
- [.task/hrm/task.md](../.task/hrm/task.md) — session-level changelog with file-by-file rationale.
- [backend/app/Services/S3UploadService.php](../backend/app/Services/S3UploadService.php) — the single point of truth.
