<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Models\User;
use App\Services\S3UploadService;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\EmployeeAddress;
use App\Tenants\Modules\HRM\Models\EmployeeContract;
use App\Tenants\Modules\HRM\Models\EmployeeEducation;
use App\Tenants\Modules\HRM\Models\EmployeeEmergencyContact;
use App\Tenants\Modules\HRM\Models\EmployeePromotion;
use App\Tenants\Modules\HRM\Models\EmployeeSpouse;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    /**
     * Top-level columns owned by the `employees` table itself.
     * Used to split a nested wizard payload into the "scalar" subset that
     * gets passed to Employee::create().
     */
    private const EMPLOYEE_COLUMNS = [
        'employee_id', 'nssf_id',
        'first_name', 'last_name', 'first_name_kh', 'last_name_kh',
        'email', 'phone', 'office_phone', 'contact_phone',
        'date_of_birth', 'gender', 'nationality',
        'national_id', 'bank_account', 'tax_id',
        'identification_type', 'id_card_number',
        'id_issued_date', 'id_issued_by', 'id_issued_place',
        'religion', 'marital_status', 'blood_group', 'children_count',
        'address', 'city', 'country',
        'department_id', 'position_id', 'role_name', 'manager_id', 'user_id',
        'hire_date', 'employment_type', 'status',
        'base_salary', 'currency', 'pay_frequency',
        // `photo_path` is set inside the service AFTER the employee row is
        // created (we need the new ID to derive the final object key), so
        // it stays OUT of the column allowlist to prevent callers from
        // injecting arbitrary keys via the create payload.
    ];

    public function __construct(
        protected WorkflowStatusService $statuses,
        protected RecruitmentService $recruitment,
        protected S3UploadService $uploads,
    ) {}

    public function create(array $data): Employee
    {
        $photoTempKey = $data['photo_temp_key'] ?? null;

        $employee = DB::transaction(function () use ($data) {
            // Carve off the nested sub-payloads — the rest is the employees row.
            $current   = $this->normalizeAddress($data['current_address']   ?? null, EmployeeAddress::TYPE_CURRENT);
            $permanent = $this->normalizeAddress($data['permanent_address'] ?? null, EmployeeAddress::TYPE_PERMANENT);
            $emergency = $this->normalizeAddress($data['emergency_address'] ?? null, EmployeeAddress::TYPE_EMERGENCY);
            $spouse           = $data['spouse']            ?? null;
            $emergencyContact = $data['emergency_contact'] ?? null;
            $educations       = $data['educations']        ?? [];
            $contract         = $data['contract']          ?? null;

            $employeeData = array_intersect_key($data, array_flip(self::EMPLOYEE_COLUMNS));
            $employeeData['status']      = $employeeData['status']      ?? $this->statuses->initialFor('hrm.employee');
            $employeeData['employee_id'] = $employeeData['employee_id'] ?? $this->recruitment->generateNextEmployeeId();

            $employee = Employee::create($employeeData);

            foreach (array_filter([$current, $permanent, $emergency]) as $addr) {
                $addr['employee_id'] = $employee->id;
                EmployeeAddress::create($addr);
            }

            if ($spouse && $this->hasAny($spouse, ['name', 'date_of_birth', 'education', 'occupation'])) {
                EmployeeSpouse::create(['employee_id' => $employee->id] + $spouse);
            }

            if ($emergencyContact && $this->hasAny($emergencyContact, [
                'father_name', 'father_occupation', 'mother_name', 'mother_occupation',
                'phone_number', 'home_phone',
            ])) {
                EmployeeEmergencyContact::create(['employee_id' => $employee->id] + $emergencyContact);
            }

            foreach ($educations as $row) {
                if (! is_array($row) || ! $this->hasAny($row, ['level', 'major_subject', 'status', 'university_school'])) {
                    continue;
                }
                $row['employee_id'] = $employee->id;
                EmployeeEducation::create($row);
            }

            if ($contract && ! empty($contract['type']) && ! empty($contract['start_date'])) {
                $contract['employee_id'] = $employee->id;
                $contract['status']      = $contract['status'] ?? EmployeeContract::STATUS_ACTIVE;
                EmployeeContract::create($contract);
            }

            return $employee;
        });

        // Photo commit runs AFTER the DB transaction commits — the temp
        // object exists in MinIO whether or not we persisted the row, and
        // the bucket's 1-day lifecycle rule eventually reclaims it if the
        // employee row never landed.
        if ($photoTempKey) {
            $this->commitEmployeePhoto($employee, $photoTempKey);
        }

        return $employee->fresh([
            'department', 'position',
            'currentAddress', 'permanentAddress', 'emergencyAddress',
            'spouse', 'emergencyContact', 'educations', 'activeContract',
        ]);
    }

    /**
     * Move an `uploads/{nanoid}.{ext}` blob to the tenant's permanent prefix
     * and stamp the resulting key onto employees.photo_path. The temp key
     * was validated by the controller's `present:uploads/` rule, so any
     * `DomainException` here is operator error (missing object, etc.).
     */
    private function commitEmployeePhoto(Employee $employee, string $tempKey): void
    {
        $ext    = pathinfo($tempKey, PATHINFO_EXTENSION) ?: 'jpg';
        $handle = function_exists('tenant') && tenant('handle') ? tenant('handle') : 'unknown';
        $final  = "tenants/{$handle}/employees/{$employee->id}/photo.{$ext}";

        $committed = $this->uploads->commitObject($tempKey, $final);
        if ($committed) {
            $employee->update(['photo_path' => $committed]);
        }
    }

    public function update(Employee $employee, array $data): Employee
    {
        $photoTempKey = $data['photo_temp_key'] ?? null;

        DB::transaction(function () use ($employee, $data) {
            if (isset($data['status']) && $data['status'] !== $employee->status) {
                $this->statuses->validateTransition('hrm.employee', $employee->status, $data['status']);
            }

            $employeeData = array_intersect_key($data, array_flip(self::EMPLOYEE_COLUMNS));
            $employee->update($employeeData);

            // ── Addresses: upsert by (employee_id, type) ────────────
            // A sub-block in the payload triggers an upsert; an absent
            // block leaves the existing row untouched. An empty-but-present
            // block (all fields null) is treated as a clear → delete.
            foreach ([
                EmployeeAddress::TYPE_CURRENT   => 'current_address',
                EmployeeAddress::TYPE_PERMANENT => 'permanent_address',
                EmployeeAddress::TYPE_EMERGENCY => 'emergency_address',
            ] as $type => $key) {
                if (! array_key_exists($key, $data)) continue;
                $normalized = $this->normalizeAddress($data[$key], $type);
                if ($normalized === null) {
                    EmployeeAddress::where('employee_id', $employee->id)->where('type', $type)->delete();
                    continue;
                }
                EmployeeAddress::updateOrCreate(
                    ['employee_id' => $employee->id, 'type' => $type],
                    $normalized,
                );
            }

            // ── Spouse (1:1) ────────────────────────────────────────
            if (array_key_exists('spouse', $data)) {
                $spouse = $data['spouse'] ?? null;
                if (is_array($spouse) && $this->hasAny($spouse, ['name', 'date_of_birth', 'education', 'occupation'])) {
                    EmployeeSpouse::updateOrCreate(
                        ['employee_id' => $employee->id],
                        array_intersect_key($spouse, array_flip(['name', 'date_of_birth', 'education', 'occupation'])),
                    );
                } else {
                    EmployeeSpouse::where('employee_id', $employee->id)->delete();
                }
            }

            // ── Emergency contact (1:1) ─────────────────────────────
            if (array_key_exists('emergency_contact', $data)) {
                $ec = $data['emergency_contact'] ?? null;
                if (is_array($ec) && $this->hasAny($ec, [
                    'father_name', 'father_occupation', 'mother_name', 'mother_occupation',
                    'phone_number', 'home_phone',
                ])) {
                    EmployeeEmergencyContact::updateOrCreate(
                        ['employee_id' => $employee->id],
                        array_intersect_key($ec, array_flip([
                            'father_name', 'father_occupation', 'mother_name', 'mother_occupation',
                            'phone_number', 'home_phone',
                        ])),
                    );
                } else {
                    EmployeeEmergencyContact::where('employee_id', $employee->id)->delete();
                }
            }

            // ── Educations (1:N): hard-replace the whole set ────────
            // The wizard only captures one education row today. If we
            // later let the user manage a list inline, switch to a
            // diff-merge by id; replace is simplest until then.
            if (array_key_exists('educations', $data)) {
                EmployeeEducation::where('employee_id', $employee->id)->delete();
                foreach ((array) ($data['educations'] ?? []) as $row) {
                    if (! is_array($row) || ! $this->hasAny($row, ['level', 'major_subject', 'status', 'university_school'])) {
                        continue;
                    }
                    $row['employee_id'] = $employee->id;
                    EmployeeEducation::create($row);
                }
            }

            // ── Contract (1:N): update the active one in place ──────
            // We keep historical contracts; the wizard only edits the
            // most-recent active row. A future "renew contract" action
            // is the right place to roll the active row to expired and
            // create a fresh one.
            if (array_key_exists('contract', $data)) {
                $contract = $data['contract'] ?? null;
                if (is_array($contract) && ! empty($contract['type']) && ! empty($contract['start_date'])) {
                    $payload = array_intersect_key($contract, array_flip([
                        'type', 'start_date', 'end_date', 'comment',
                    ]));
                    $active = $employee->contracts()->where('status', EmployeeContract::STATUS_ACTIVE)->first();
                    if ($active) {
                        $active->update($payload);
                    } else {
                        $payload['employee_id'] = $employee->id;
                        $payload['status']      = EmployeeContract::STATUS_ACTIVE;
                        EmployeeContract::create($payload);
                    }
                }
            }
        });

        // Photo commit after the transaction succeeds (same as create()).
        if ($photoTempKey) {
            $this->commitEmployeePhoto($employee, $photoTempKey);
        }

        return $employee->fresh([
            'department', 'position',
            'currentAddress', 'permanentAddress', 'emergencyAddress',
            'spouse', 'emergencyContact', 'educations', 'activeContract',
        ]);
    }

    /**
     * Soft-terminates an employee. Sets the terminal status, stamps the
     * termination date, and soft-deletes so the row drops out of the
     * `employees_email_active_unique` partial index — freeing the email
     * for a possible re-hire.
     */
    public function terminateEmployee(Employee $employee, ?string $reason = null, ?string $effectiveAt = null): Employee
    {
        return DB::transaction(function () use ($employee, $reason, $effectiveAt) {
            $this->statuses->validateTransition('hrm.employee', $employee->status, 'terminated');

            $employee->update([
                'status'           => 'terminated',
                'termination_date' => $effectiveAt ?? now()->toDateString(),
            ]);

            if ($reason) {
                // Stash the reason as a manager note so it surfaces in audit history.
                $employee->setRelation('terminationReason', $reason);
            }

            $employee->delete(); // soft delete

            return $employee;
        });
    }

    /**
     * Reactivate a soft-deleted employee (within reason — same email can't
     * collide with a new live row, the partial unique index would block it).
     */
    public function restoreEmployee(Employee $employee): Employee
    {
        if (! $employee->trashed()) {
            throw new DomainException('Employee is not terminated.');
        }
        $employee->restore();
        $employee->update(['status' => 'active', 'termination_date' => null]);
        return $employee->refresh();
    }

    /**
     * Record a position/department/salary change in the career journal,
     * and (when `apply_now=true`) also update the live Employee row.
     *
     * `apply_now=false` is for backfilling historical events (e.g. "the
     * person was promoted in 2024, we're entering it now for the audit
     * trail") where the current Employee row should NOT shift.
     *
     * Snapshots: the controller computes the `previous_*` fields from
     * the current Employee row before calling this method, so the
     * journal always shows the true delta even when subsequent edits
     * mutate the live row later.
     */
    public function recordPromotion(Employee $employee, array $data): EmployeePromotion
    {
        $applyNow = (bool) ($data['apply_now'] ?? true);

        return DB::transaction(function () use ($employee, $data, $applyNow) {
            $promotion = EmployeePromotion::create([
                'employee_id'            => $employee->id,
                'effective_date'         => $data['effective_date'],
                'type'                   => $data['type'] ?? 'promotion',
                'previous_position_id'   => $data['previous_position_id']   ?? $employee->position_id,
                'new_position_id'        => $data['new_position_id']        ?? null,
                'previous_department_id' => $data['previous_department_id'] ?? $employee->department_id,
                'new_department_id'      => $data['new_department_id']      ?? null,
                'previous_role_name'     => $data['previous_role_name']     ?? $employee->role_name,
                'new_role_name'          => $data['new_role_name']          ?? null,
                'previous_salary'        => $data['previous_salary']        ?? $employee->base_salary,
                'new_salary'             => $data['new_salary']             ?? null,
                'currency'               => $data['currency']               ?? $employee->currency,
                'reason'                 => $data['reason']                 ?? null,
                'approved_by'            => $data['approved_by']            ?? null,
            ]);

            if ($applyNow) {
                // Only overwrite fields the caller actually supplied — a
                // pure salary adjustment doesn't touch position; a lateral
                // move doesn't touch salary.
                $updates = [];
                if (! empty($data['new_position_id']))   $updates['position_id']   = $data['new_position_id'];
                if (! empty($data['new_department_id'])) $updates['department_id'] = $data['new_department_id'];
                if (! empty($data['new_role_name']))     $updates['role_name']     = $data['new_role_name'];
                if (! empty($data['new_salary']))        $updates['base_salary']   = $data['new_salary'];
                if ($updates) $employee->forceFill($updates)->save();
            }

            return $promotion->fresh([
                'previousPosition:id,title', 'newPosition:id,title',
                'previousDepartment:id,name', 'newDepartment:id,name',
                'approver:id,first_name,last_name,employee_id',
            ]);
        });
    }

    /**
     * Provision a tenant `users` row for this employee and link it via
     * `employees.user_id`. Throws if the employee already has an account
     * so accidental double-create can't orphan the previous user row.
     *
     * `password` arrives in plaintext from the caller (the wizard either
     * accepts it typed or shows a frontend-generated one). The User model
     * casts `password => 'hashed'`, so Eloquent bcrypts it on save.
     */
    public function createUserAccount(Employee $employee, array $data): User
    {
        return DB::transaction(function () use ($employee, $data) {
            if ($employee->user_id) {
                throw new DomainException('This employee already has a user account.');
            }

            // Derive a handle from the email's local-part when the caller
            // doesn't supply one. Strip anything outside [a-z0-9._-] so the
            // resulting handle is URL-safe.
            $handle = $data['handle'] ?? null;
            if (! $handle) {
                $localPart = strstr($data['email'], '@', true) ?: $data['email'];
                $handle = preg_replace('/[^a-z0-9._-]/i', '', $localPart);
            }

            $user = User::create([
                'name'      => trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')),
                'handle'    => $handle,
                'email'     => $data['email'],
                'password'  => $data['password'],
                'role_id'   => $data['role_id'],
                'is_active' => true,
            ]);

            $employee->forceFill(['user_id' => $user->id])->save();

            return $user;
        });
    }

    /**
     * Validate and shape an address sub-payload. Returns null when the
     * caller didn't include this address type at all, so the service can
     * skip creating an empty row.
     */
    private function normalizeAddress(mixed $raw, string $type): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $hasContent = $this->hasAny($raw, [
            'home_number', 'street', 'group',
            'province_code', 'district_code', 'commune_code', 'village_code',
            'lat', 'lng',
        ]);
        if (! $hasContent) {
            return null;
        }
        return [
            'type'          => $type,
            'home_number'   => $raw['home_number']   ?? null,
            'street'        => $raw['street']        ?? null,
            'province_code' => $raw['province_code'] ?? null,
            'district_code' => $raw['district_code'] ?? null,
            'commune_code'  => $raw['commune_code']  ?? null,
            'village_code'  => $raw['village_code']  ?? null,
            'group'         => $raw['group']         ?? null,
            'lat'           => $raw['lat']           ?? null,
            'lng'           => $raw['lng']           ?? null,
        ];
    }

    private function hasAny(array $row, array $keys): bool
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) {
                return true;
            }
        }
        return false;
    }
}
