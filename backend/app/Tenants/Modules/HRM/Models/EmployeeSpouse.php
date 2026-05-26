<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1:1 with Employee — PK is `employee_id`. No auto-UUID generation needed
 * because the parent supplies the FK value.
 */
class EmployeeSpouse extends Model
{
    use Auditable;

    protected $primaryKey = 'employee_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
