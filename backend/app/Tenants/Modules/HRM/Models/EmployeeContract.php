<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeContract extends Model
{
    use HasUuids, Auditable;

    public const STATUS_ACTIVE     = 'active';
    public const STATUS_EXPIRED    = 'expired';
    public const STATUS_TERMINATED = 'terminated';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
