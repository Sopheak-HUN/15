<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\LeaveBalance;
use App\Tenants\Modules\HRM\Models\LeaveRequest;
use App\Tenants\Modules\HRM\Models\LeaveType;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class LeaveService
{
    public function __construct(protected WorkflowStatusService $statuses) {}

    /**
     * Submit a new leave request. Locks the matching LeaveBalance row to
     * make the available-balance check race-safe across concurrent requests.
     */
    public function submitRequest(Employee $employee, LeaveType $type, array $data): LeaveRequest
    {
        $durationType = $data['duration_type'] ?? LeaveRequest::DURATION_FULL_DAY;

        // Half-day forces a single-day window and a fixed 0.5-day cost
        // against the balance. Caller's `end_date` and `days` are ignored
        // when duration_type is half_day so the math can't disagree.
        if ($durationType === LeaveRequest::DURATION_HALF_DAY) {
            $start = CarbonImmutable::parse($data['start_date']);
            $end   = $start;
            $days  = 0.5;
        } else {
            $start = CarbonImmutable::parse($data['start_date']);
            $end   = CarbonImmutable::parse($data['end_date']);
            if ($end->lt($start)) {
                throw new DomainException('End date must be on or after start date.');
            }
            $days = (float) ($data['days'] ?? $start->diffInDays($end) + 1);
        }

        if ($days <= 0) {
            throw new DomainException('Leave duration must be positive.');
        }

        return DB::transaction(function () use ($employee, $type, $data, $start, $end, $days, $durationType) {
            $balance = LeaveBalance::lockForUpdate()
                ->firstOrCreate(
                    [
                        'employee_id'   => $employee->id,
                        'leave_type_id' => $type->id,
                        'year'          => $start->year,
                    ],
                    ['balance' => $type->default_balance, 'used' => 0, 'pending' => 0]
                );

            if ($balance->available() < $days) {
                throw new DomainException(sprintf(
                    'Insufficient %s balance: requested %.2f, available %.2f.',
                    $type->name, $days, $balance->available()
                ));
            }

            $request = LeaveRequest::create([
                'employee_id'    => $employee->id,
                'leave_type_id'  => $type->id,
                'duration_type'  => $durationType,
                'start_date'     => $start,
                'end_date'       => $end,
                'days'           => $days,
                'reason'         => $data['reason'] ?? null,
                'assign_to'      => $data['assign_to'] ?? null,
                'reference_path' => $data['reference_path'] ?? null,
                'status'         => $this->statuses->initialFor('hrm.leave'),
            ]);

            $balance->increment('pending', $days);

            return $request;
        });
    }

    public function approve(LeaveRequest $request, ?Employee $approver): LeaveRequest
    {
        return DB::transaction(function () use ($request, $approver) {
            $this->statuses->validateTransition('hrm.leave', $request->status, 'approved');

            $balance = LeaveBalance::lockForUpdate()
                ->where([
                    'employee_id'   => $request->employee_id,
                    'leave_type_id' => $request->leave_type_id,
                    'year'          => $request->start_date->year,
                ])->firstOrFail();

            $balance->decrement('pending', (float) $request->days);
            $balance->increment('used',    (float) $request->days);

            $request->update([
                'status'      => 'approved',
                'approved_by' => $approver?->id,
                'approved_at' => now(),
            ]);
            return $request->refresh();
        });
    }

    public function reject(LeaveRequest $request, ?Employee $approver, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $approver, $reason) {
            $this->statuses->validateTransition('hrm.leave', $request->status, 'rejected');

            $balance = LeaveBalance::lockForUpdate()
                ->where([
                    'employee_id'   => $request->employee_id,
                    'leave_type_id' => $request->leave_type_id,
                    'year'          => $request->start_date->year,
                ])->firstOrFail();

            $balance->decrement('pending', (float) $request->days);

            $request->update([
                'status'           => 'rejected',
                'approved_by'      => $approver?->id,
                'approved_at'      => now(),
                'rejection_reason' => $reason,
            ]);
            return $request->refresh();
        });
    }

    /**
     * Accrue a fixed number of days into the current year's balance.
     * Called from a scheduled job (e.g. monthly) per tenant policy.
     */
    public function accrue(Employee $employee, LeaveType $type, float $days): LeaveBalance
    {
        if (! $type->accrues) {
            throw new DomainException("Leave type '{$type->code}' does not accrue.");
        }
        $year = now()->year;
        return DB::transaction(function () use ($employee, $type, $days, $year) {
            $balance = LeaveBalance::lockForUpdate()->firstOrCreate(
                ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year],
                ['balance' => 0, 'used' => 0, 'pending' => 0]
            );
            $balance->increment('balance', $days);
            return $balance->refresh();
        });
    }
}
