<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Tenants\Modules\HRM\Models\Attendance;
use App\Tenants\Modules\HRM\Models\Employee;
use DomainException;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * Individual employee check-in.
     */
    public function checkIn(Employee $employee, ?string $notes = null): Attendance
    {
        if ($employee->status === 'terminated') {
            throw new DomainException('Terminated employees cannot check in.');
        }

        $today = now()->toDateString();

        return DB::transaction(function () use ($employee, $today, $notes) {
            $existing = Attendance::where('employee_id', $employee->id)
                ->where('date', $today)
                ->first();

            if ($existing && $existing->check_in) {
                throw new DomainException('Already checked in for today.');
            }

            $time = now()->toTimeString();
            $status = $time > '09:00:00' ? 'late' : 'present';

            if ($existing) {
                $existing->update([
                    'check_in' => now(),
                    'status'   => $status,
                    'notes'    => $notes ? trim(($existing->notes ?? '') . "\n" . $notes) : $existing->notes,
                ]);
                return $existing->refresh();
            }

            return Attendance::create([
                'employee_id' => $employee->id,
                'date'        => $today,
                'check_in'    => now(),
                'status'      => $status,
                'notes'       => $notes,
            ]);
        });
    }

    /**
     * Individual employee check-out.
     */
    public function checkOut(Employee $employee, ?string $notes = null): Attendance
    {
        $today = now()->toDateString();

        return DB::transaction(function () use ($employee, $today, $notes) {
            $attendance = Attendance::where('employee_id', $employee->id)
                ->where('date', $today)
                ->first();

            if (! $attendance || ! $attendance->check_in) {
                throw new DomainException('No check-in record found for today.');
            }

            if ($attendance->check_out) {
                throw new DomainException('Already checked out for today.');
            }

            $attendance->update([
                'check_out' => now(),
                'notes'     => $notes ? trim(($attendance->notes ?? '') . "\n" . $notes) : $attendance->notes,
            ]);

            return $attendance->refresh();
        });
    }

    /**
     * Manual log or override by HR.
     */
    public function logAttendance(
        string $employeeId,
        string $date,
        string $status,
        ?string $checkIn = null,
        ?string $checkOut = null,
        ?string $notes = null
    ): Attendance {
        // Validate employee exists
        $employeeExists = Employee::where('id', $employeeId)->exists();
        if (! $employeeExists) {
            throw new DomainException('Employee not found.');
        }

        return DB::transaction(function () use ($employeeId, $date, $status, $checkIn, $checkOut, $notes) {
            return Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $date],
                [
                    'status'    => $status,
                    'check_in'  => $checkIn,
                    'check_out' => $checkOut,
                    'notes'     => $notes,
                ]
            );
        });
    }

    /**
     * Aggregate attendance stats for an employee in a date range.
     */
    public function getStats(string $employeeId, string $startDate, string $endDate): array
    {
        $counts = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $defaults = [
            'present'  => 0,
            'late'     => 0,
            'absent'   => 0,
            'half_day' => 0,
            'on_leave' => 0,
        ];

        return array_merge($defaults, $counts);
    }
}
