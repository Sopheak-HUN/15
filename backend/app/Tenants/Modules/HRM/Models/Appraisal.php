<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appraisal extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected $casts = [
        'responses'     => 'array',
        'overall_score' => 'decimal:2',
        'submitted_at'  => 'datetime',
        'closed_at'     => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }
}
