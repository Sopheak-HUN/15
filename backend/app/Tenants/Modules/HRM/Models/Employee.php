<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Models\User;
use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasUuids, SoftDeletes, Auditable;

    protected $guarded = [];

    /**
     * Sensitive PII columns are stored encrypted at rest. Salary is included
     * because DB grep / replication snapshots are common audit findings.
     */
    protected $casts = [
        'date_of_birth'    => 'date',
        'hire_date'        => 'date',
        'termination_date' => 'date',
        'national_id'      => 'encrypted',
        'bank_account'     => 'encrypted',
        'tax_id'           => 'encrypted',
        'base_salary'      => 'encrypted',
    ];

    protected $hidden = ['national_id', 'bank_account', 'tax_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function payComponents(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
