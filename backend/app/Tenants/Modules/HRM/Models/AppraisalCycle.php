<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppraisalCycle extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'rating_scale' => 'array',
        'is_active'    => 'boolean',
    ];

    public function appraisals(): HasMany
    {
        return $this->hasMany(Appraisal::class, 'cycle_id');
    }
}
