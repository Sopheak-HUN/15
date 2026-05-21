<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'used'    => 'decimal:2',
        'pending' => 'decimal:2',
        'year'    => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function available(): float
    {
        return (float) $this->balance - (float) $this->used - (float) $this->pending;
    }
}
