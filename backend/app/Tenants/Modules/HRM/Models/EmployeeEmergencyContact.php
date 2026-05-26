<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1:1 with Employee — PK is `employee_id`. Address fields are NOT stored
 * here; the emergency address lives in employee_addresses with type='emergency'.
 */
class EmployeeEmergencyContact extends Model
{
    use Auditable;

    protected $primaryKey = 'employee_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $guarded = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
