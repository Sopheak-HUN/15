<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Tenants\Modules\HRM\Models\Attendance;
use App\Tenants\Modules\HRM\Models\Employee;
use DomainException;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /**
     * The 4-punch day: check_in → break_out → break_in → check_out.
     * Each call enforces ordering so the timeline can't go backwards
     * (e.g. you can't break_out before checking in).
     *
     * Per-half status: a morning is "late" when check_in lands after
     * MORNING_LATE_AFTER; afternoon is "late" when break_in lands after
     * AFTERNOON_LATE_AFTER. The legacy `status` column gets the worse
     * of the two so older queries still mean something.
     */
    private const MORNING_LATE_AFTER   = '09:00:00';
    private const AFTERNOON_LATE_AFTER = '13:30:00';

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

            $morningStatus = $this->morningStatusFor(now()->toTimeString());

            if ($existing) {
                $existing->update([
                    'check_in'       => now(),
                    'morning_status' => $morningStatus,
                    'status'         => $this->rollupStatus($morningStatus, $existing->afternoon_status),
                    'notes'          => $this->appendNote($existing->notes, $notes),
                ]);
                return $existing->refresh();
            }

            return Attendance::create([
                'employee_id'    => $employee->id,
                'date'           => $today,
                'check_in'       => now(),
                'morning_status' => $morningStatus,
                'status'         => $morningStatus,
                'notes'          => $notes,
            ]);
        });
    }

    /**
     * Punch out for lunch / midday break. Must follow check_in.
     */
    public function breakOut(Employee $employee, ?string $notes = null): Attendance
    {
        $today = now()->toDateString();

        return DB::transaction(function () use ($employee, $today, $notes) {
            $row = Attendance::where('employee_id', $employee->id)->where('date', $today)->first();
            if (! $row || ! $row->check_in) {
                throw new DomainException('You must check in before going on break.');
            }
            if ($row->break_out) {
                throw new DomainException('Already on break.');
            }
            $row->update([
                'break_out' => now(),
                'notes'     => $this->appendNote($row->notes, $notes),
            ]);
            return $row->refresh();
        });
    }

    /**
     * Punch in after lunch — second-half start. Stamps afternoon_status.
     */
    public function breakIn(Employee $employee, ?string $notes = null): Attendance
    {
        $today = now()->toDateString();

        return DB::transaction(function () use ($employee, $today, $notes) {
            $row = Attendance::where('employee_id', $employee->id)->where('date', $today)->first();
            if (! $row || ! $row->break_out) {
                throw new DomainException('You must break out first.');
            }
            if ($row->break_in) {
                throw new DomainException('Already back from break.');
            }
            $afternoonStatus = $this->afternoonStatusFor(now()->toTimeString());
            $row->update([
                'break_in'         => now(),
                'afternoon_status' => $afternoonStatus,
                'status'           => $this->rollupStatus($row->morning_status, $afternoonStatus),
                'notes'            => $this->appendNote($row->notes, $notes),
            ]);
            return $row->refresh();
        });
    }

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
            // Lenient: not requiring break_in here because some shifts
            // skip lunch entirely. If break_out is set but break_in
            // isn't, the afternoon shows as "absent" via the cron, not
            // through this path.

            $attendance->update([
                'check_out' => now(),
                'notes'     => $this->appendNote($attendance->notes, $notes),
            ]);

            return $attendance->refresh();
        });
    }

    /**
     * Manual log or override by HR. Accepts per-half status when the
     * caller supplies it; otherwise falls back to the legacy `status`.
     */
    public function logAttendance(
        string $employeeId,
        string $date,
        string $status,
        ?string $checkIn = null,
        ?string $checkOut = null,
        ?string $notes = null,
        ?string $morningStatus = null,
        ?string $afternoonStatus = null,
        ?string $breakOut = null,
        ?string $breakIn = null
    ): Attendance {
        if (! Employee::where('id', $employeeId)->exists()) {
            throw new DomainException('Employee not found.');
        }

        return DB::transaction(function () use (
            $employeeId, $date, $status, $checkIn, $checkOut, $notes,
            $morningStatus, $afternoonStatus, $breakOut, $breakIn
        ) {
            return Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $date],
                [
                    'status'           => $status,
                    'morning_status'   => $morningStatus ?? $status,
                    'afternoon_status' => $afternoonStatus ?? $status,
                    'check_in'         => $checkIn,
                    'break_out'        => $breakOut,
                    'break_in'         => $breakIn,
                    'check_out'        => $checkOut,
                    'notes'            => $notes,
                ]
            );
        });
    }

    /**
     * Aggregate attendance stats for an employee in a date range. We
     * sum across morning + afternoon halves so "5 present days" really
     * means 5 full days (10 half-days). The legacy `status` column is
     * used as a fallback for rows without per-half fields.
     */
    public function getStats(string $employeeId, string $startDate, string $endDate): array
    {
        $rows = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get(['status', 'morning_status', 'afternoon_status']);

        $defaults = ['present' => 0, 'late' => 0, 'absent' => 0, 'half_day' => 0, 'on_leave' => 0];
        $counts = $defaults;

        foreach ($rows as $row) {
            // Per-half counters when available — otherwise both halves
            // inherit the row's overall `status`. Halves count as 0.5.
            $m = $row->morning_status   ?: $row->status;
            $a = $row->afternoon_status ?: $row->status;

            foreach ([$m, $a] as $half) {
                if ($half && isset($counts[$half])) {
                    $counts[$half] += 0.5;
                }
            }
        }

        // Round to integers — a half-day stays as 0.5 only via the
        // half_day bucket; everything else aggregates whole days.
        foreach ($counts as $k => $v) {
            $counts[$k] = (int) round($v);
        }

        return $counts;
    }

    /**
     * End-of-day backfill: every active employee who didn't punch in on
     * `$date` gets a row with both halves marked absent. Re-running is
     * safe — the partial unique index on (employee_id, date) means we
     * upsert via updateOrCreate without duplicating rows.
     *
     * @return int  number of absent rows created or updated
     */
    public function markAbsentForDate(string $date): int
    {
        $touched = 0;

        Employee::where('status', 'active')->select(['id'])->chunkById(200, function ($employees) use ($date, &$touched) {
            foreach ($employees as $emp) {
                $row = Attendance::where('employee_id', $emp->id)->where('date', $date)->first();
                if (! $row) {
                    Attendance::create([
                        'employee_id'      => $emp->id,
                        'date'             => $date,
                        'morning_status'   => 'absent',
                        'afternoon_status' => 'absent',
                        'status'           => 'absent',
                    ]);
                    $touched++;
                    continue;
                }
                $patch = [];
                if (! $row->check_in) $patch['morning_status'] = 'absent';
                if (! $row->break_in) $patch['afternoon_status'] = 'absent';
                if ($patch) {
                    $patch['status'] = $this->rollupStatus(
                        $patch['morning_status']   ?? $row->morning_status,
                        $patch['afternoon_status'] ?? $row->afternoon_status,
                    );
                    $row->update($patch);
                    $touched++;
                }
            }
        });

        return $touched;
    }

    private function morningStatusFor(string $time): string
    {
        return $time > self::MORNING_LATE_AFTER ? 'late' : 'present';
    }

    private function afternoonStatusFor(string $time): string
    {
        return $time > self::AFTERNOON_LATE_AFTER ? 'late' : 'present';
    }

    /**
     * Worst-of-two rollup. Used so the legacy `status` column reflects
     * the day's overall outcome — "late" beats "present", "absent"
     * beats both. on_leave is treated as the worst because a leave day
     * shouldn't be summarized as "present".
     */
    private function rollupStatus(?string $morning, ?string $afternoon): string
    {
        $rank = ['present' => 1, 'late' => 2, 'half_day' => 3, 'absent' => 4, 'on_leave' => 5];
        $m = $morning   ?: 'present';
        $a = $afternoon ?: 'present';
        return ($rank[$a] ?? 0) > ($rank[$m] ?? 0) ? $a : $m;
    }

    private function appendNote(?string $existing, ?string $incoming): ?string
    {
        if (! $incoming) return $existing;
        return $existing ? trim($existing . "\n" . $incoming) : $incoming;
    }
}
