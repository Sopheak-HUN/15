<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayComponent extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected $casts = [
        'amount'      => 'decimal:4',
        'is_taxable'  => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }
}
