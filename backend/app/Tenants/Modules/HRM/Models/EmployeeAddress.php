<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAddress extends Model
{
    use HasUuids, Auditable;

    public const TYPE_CURRENT   = 'current';
    public const TYPE_PERMANENT = 'permanent';
    public const TYPE_EMERGENCY = 'emergency';

    protected $guarded = [];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
