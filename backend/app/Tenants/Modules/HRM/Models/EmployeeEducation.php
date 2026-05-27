<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEducation extends Model
{
    use HasUuids, Auditable;

    // Eloquent's inflector treats "education" as uncountable and would
    // resolve the table name to `employee_education`. Lock it down to the
    // actual migration name.
    protected $table = 'employee_educations';

    protected $guarded = [];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
