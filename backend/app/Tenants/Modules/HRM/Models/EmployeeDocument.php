<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Services\S3UploadService;
use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDocument extends Model
{
    use HasUuids, SoftDeletes, Auditable;

    protected $guarded = [];

    protected $casts = [
        'issued_at'   => 'date',
        'expires_at'  => 'date',
        'size_bytes'  => 'integer',
    ];

    /**
     * Append a short-lived presigned GET URL so the frontend can render
     * a click-through download link without holding raw bucket creds.
     * `file_path` is the object key inside the `erp-uploads` bucket
     * (e.g. tenants/{handle}/employees/{id}/documents/{nanoid}.pdf).
     */
    protected $appends = ['download_url'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected function downloadUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->file_path) {
                    return null;
                }
                // Only sign keys that look like object-storage paths
                // (`tenants/...`). Legacy rows whose `file_path` is just a
                // free-form descriptor get a null download URL.
                if (! str_starts_with($this->file_path, 'tenants/')) {
                    return null;
                }
                return app(S3UploadService::class)->signGet($this->file_path);
            },
        )->shouldCache();
    }
}
