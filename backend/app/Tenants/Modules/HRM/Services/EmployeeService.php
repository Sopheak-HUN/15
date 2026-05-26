<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Services\S3UploadService;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\EmployeeAddress;
use App\Tenants\Modules\HRM\Models\EmployeeContract;
use App\Tenants\Modules\HRM\Models\EmployeeEducation;
use App\Tenants\Modules\HRM\Models\EmployeeEmergencyContact;
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
        return DB::transaction(function () use ($employee, $data) {
            if (isset($data['status']) && $data['status'] !== $employee->status) {
                $this->statuses->validateTransition('hrm.employee', $employee->status, $data['status']);
            }
            $employeeData = array_intersect_key($data, array_flip(self::EMPLOYEE_COLUMNS));
            $employee->update($employeeData);
            return $employee->refresh();
        });
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
