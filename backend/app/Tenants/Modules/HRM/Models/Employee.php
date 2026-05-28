<?php

namespace App\Tenants\Modules\HRM\Models;

use App\Models\User;
use App\Services\S3UploadService;
use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'id_issued_date'   => 'date',
        'children_count'   => 'integer',
        'national_id'      => 'encrypted',
        'id_card_number'   => 'encrypted',
        'bank_account'     => 'encrypted',
        'tax_id'           => 'encrypted',
        'base_salary'      => 'encrypted',
    ];

    protected $hidden = ['national_id', 'id_card_number', 'bank_account', 'tax_id'];

    /**
     * Always include the freshly signed photo URL in JSON responses so the
     * frontend can render the image without holding raw bucket credentials.
     */
    protected $appends = ['photo_url'];

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

    /**
     * Career journal: every promotion/transfer/salary adjustment. Ordered
     * by effective_date desc on the controller side so the timeline reads
     * top-down newest-first.
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(EmployeePromotion::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function payComponents(): HasMany
    {
        return $this->hasMany(EmployeePayComponent::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(EmployeeAddress::class);
    }

    public function currentAddress(): HasOne
    {
        return $this->hasOne(EmployeeAddress::class)->where('type', EmployeeAddress::TYPE_CURRENT);
    }

    public function permanentAddress(): HasOne
    {
        return $this->hasOne(EmployeeAddress::class)->where('type', EmployeeAddress::TYPE_PERMANENT);
    }

    public function emergencyAddress(): HasOne
    {
        return $this->hasOne(EmployeeAddress::class)->where('type', EmployeeAddress::TYPE_EMERGENCY);
    }

    public function spouse(): HasOne
    {
        return $this->hasOne(EmployeeSpouse::class);
    }

    public function emergencyContact(): HasOne
    {
        return $this->hasOne(EmployeeEmergencyContact::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function activeContract(): HasOne
    {
        // Plain HasOne filtered by status. We deliberately avoid
        // `latestOfMany()` because Laravel's subquery uses `MAX(id)` as a
        // tiebreaker and our PKs are UUIDs (Postgres has no MAX(uuid)).
        // Business rule: at any time an employee has at most one contract
        // with status='active' — older contracts are moved to expired/
        // terminated when a new one is signed. If duplicates ever appear
        // it's a data bug, not something this relation should silently
        // paper over.
        return $this->hasOne(EmployeeContract::class)
            ->where('status', EmployeeContract::STATUS_ACTIVE);
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Presigned GET URL for the photo (5-minute TTL), or null when the row
     * has no photo. The bucket itself stays private — every fetch costs one
     * presign, but at this scale it's cheap and avoids the public-ACL trap.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->photo_path) {
                    return null;
                }
                return app(S3UploadService::class)->signGet($this->photo_path);
            },
        )->shouldCache(); // memo for the lifetime of the request
    }
}
