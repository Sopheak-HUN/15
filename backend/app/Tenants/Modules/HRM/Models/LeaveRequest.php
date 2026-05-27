<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Services\S3UploadService;
use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use HasUuids, SoftDeletes, Auditable;

    public const DURATION_FULL_DAY = 'full_day';
    public const DURATION_HALF_DAY = 'half_day';

    protected $guarded = [];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'days'        => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /** Surface the presigned reference-file URL on every JSON response. */
    protected $appends = ['reference_url'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    /** The colleague the requester is delegating their work to. */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assign_to');
    }

    /**
     * Presigned GET URL for the uploaded reference file (5-min TTL).
     * Returns null when no file was attached, or when the legacy
     * `reference_path` doesn't look like an object-storage key.
     */
    protected function referenceUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->reference_path) {
                    return null;
                }
                if (! str_starts_with($this->reference_path, 'tenants/')) {
                    return null;
                }
                return app(S3UploadService::class)->signGet($this->reference_path);
            },
        )->shouldCache();
    }
}
