<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(
        protected WorkflowStatusService $statuses,
        protected RecruitmentService $recruitment,
    ) {}

    public function create(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $data['status']      = $data['status']      ?? $this->statuses->initialFor('hrm.employee');
            // Auto-issue the next `TT-NNNN` id when the caller didn't supply one.
            // Matches the recruitment convert-to-employee flow.
            $data['employee_id'] = $data['employee_id'] ?? $this->recruitment->generateNextEmployeeId();
            return Employee::create($data);
        });
    }

    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            if (isset($data['status']) && $data['status'] !== $employee->status) {
                $this->statuses->validateTransition('hrm.employee', $employee->status, $data['status']);
            }
            $employee->update($data);
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
}
