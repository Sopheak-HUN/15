<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tenant-scoped helper for the MinIO/S3 presigned-URL upload flow.
 *
 * Two S3 clients are needed because the *signing* endpoint (the one
 * stamped into the URL the browser uses) differs from the *runtime*
 * endpoint Laravel uses to talk to MinIO over the Docker network:
 *
 *   AWS_ENDPOINT          http://minio:9000        ← Laravel ↔ MinIO
 *   AWS_PUBLIC_ENDPOINT   http://localhost:9000    ← Browser ↔ MinIO
 *
 * For commit ops (copy / delete) we use the default `s3` disk which
 * uses AWS_ENDPOINT. For presign URL generation we instantiate a
 * second client pointed at AWS_PUBLIC_ENDPOINT.
 */
class S3UploadService
{
    /** Bucket all tenants share. Per-tenant isolation is enforced via key prefix. */
    public function bucket(): string
    {
        return (string) config('filesystems.disks.s3.bucket');
    }

    /**
     * Sign a 10-minute presigned PUT URL for the browser to upload a file
     * directly into the `uploads/` prefix. The returned key is a one-time
     * scratch path — `commitObject()` moves it under the tenant's final
     * prefix and the bucket's 1-day lifecycle rule cleans up abandoned ones.
     */
    public function signEmployeePhotoPut(string $mime, int $maxBytes = 2 * 1024 * 1024): array
    {
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            default      => throw new \DomainException('Unsupported image type'),
        };

        $key = 'uploads/' . Str::random(40) . '.' . $ext;

        $client = $this->publicClient();
        $cmd = $client->getCommand('PutObject', [
            'Bucket'        => $this->bucket(),
            'Key'           => $key,
            'ContentType'   => $mime,
            'ContentLength' => $maxBytes, // hint only; server enforces via Content-Length
        ]);
        $request = $client->createPresignedRequest($cmd, '+10 minutes');

        return [
            'upload_url' => (string) $request->getUri(),
            'key'        => $key,
            'mime'       => $mime,
            'max_bytes'  => $maxBytes,
            'expires_in' => 600,
        ];
    }

    /**
     * Issue a 5-minute presigned GET URL for a stored object. Used in the
     * Employee resource so the frontend never embeds raw bucket URLs.
     */
    public function signGet(string $key, string $ttl = '+5 minutes'): string
    {
        $client = $this->publicClient();
        $cmd = $client->getCommand('GetObject', [
            'Bucket' => $this->bucket(),
            'Key'    => $key,
        ]);
        return (string) $client->createPresignedRequest($cmd, $ttl)->getUri();
    }

    /**
     * Move a temp upload (`uploads/{nanoid}.{ext}`) into the tenant's
     * permanent prefix. Caller supplies the desired final key; we verify
     * the source lives under `uploads/` to prevent arbitrary copy-from
     * abuse via a forged `photo_temp_key`.
     *
     * Returns the final key on success, null when the source is missing.
     */
    public function commitObject(string $sourceKey, string $finalKey): ?string
    {
        if (! str_starts_with($sourceKey, 'uploads/')) {
            throw new \DomainException('Invalid upload key: must be under uploads/');
        }
        $disk = Storage::disk('s3');
        if (! $disk->exists($sourceKey)) {
            return null;
        }
        $disk->copy($sourceKey, $finalKey);
        $disk->delete($sourceKey);
        return $finalKey;
    }

    public function delete(string $key): void
    {
        Storage::disk('s3')->delete($key);
    }

    /**
     * S3 client whose `endpoint` is the **browser-facing** host. Used
     * exclusively for createPresignedRequest() — the URL it produces is
     * handed to the browser, which has to be able to resolve the host.
     */
    private function publicClient(): S3Client
    {
        $endpoint = (string) config('filesystems.disks.s3.public_endpoint')
                  ?: (string) config('filesystems.disks.s3.endpoint');

        return new S3Client([
            'version'                 => 'latest',
            'region'                  => (string) config('filesystems.disks.s3.region', 'us-east-1'),
            'endpoint'                => $endpoint,
            'use_path_style_endpoint' => (bool) config('filesystems.disks.s3.use_path_style_endpoint', true),
            'credentials' => [
                'key'    => (string) config('filesystems.disks.s3.key'),
                'secret' => (string) config('filesystems.disks.s3.secret'),
            ],
        ]);
    }
}
