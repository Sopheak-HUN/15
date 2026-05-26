<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Tenants\Modules\HRM\Models\Application;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecruitmentService
{
    public const EMPLOYEE_ID_PREFIX             = 'TT';
    public const EMPLOYEE_ID_PAD                = 4;
    public const REVERT_CONVERSION_WINDOW_DAYS  = 7;

    public function __construct(protected WorkflowStatusService $statuses) {}

    /**
     * Move an application along its workflow (applied → screening → ... → hired).
     * Does NOT create an Employee — that's an explicit, separate step via
     * convertToEmployee(). The two are deliberately decoupled.
     */
    public function transitionApplication(Application $application, string $to): Application
    {
        $this->statuses->validateTransition('hrm.application', $application->status, $to);
        $application->update(['status' => $to]);
        return $application->refresh();
    }

    /**
     * Convert a HIRED application into a workforce-registry Employee.
     * Idempotent — repeat calls return the existing employee.
     * Dedupe-by-email — links to an active Employee with the same email.
     */
    public function convertToEmployee(Application $application): array
    {
        if ($application->status !== 'hired') {
            throw new DomainException('Application must be in hired status before conversion.');
        }

        return DB::transaction(function () use ($application) {
            // Already linked → idempotent return.
            if ($application->employee_id) {
                $employee = Employee::find($application->employee_id);
                if ($employee) {
                    return ['employee' => $employee, 'linkedExisting' => false, 'fresh' => false];
                }
            }

            // Dedupe by active email (terminated rows are soft-deleted out of
            // the partial unique index, so they don't collide).
            $existing = Employee::where('email', $application->email)->first();
            if ($existing) {
                $application->update([
                    'employee_id'  => $existing->id,
                    'converted_at' => now(),
                ]);
                return ['employee' => $existing, 'linkedExisting' => true, 'fresh' => false];
            }

            $vacancy = $application->vacancy;
            $employee = Employee::create([
                'employee_id'     => $this->generateNextEmployeeId(),
                'first_name'      => $application->first_name,
                'last_name'       => $application->last_name,
                'email'           => $application->email,
                'phone'           => $application->phone,
                'department_id'   => $vacancy?->department_id,
                'position_id'     => $vacancy?->position_id,
                'hire_date'       => now()->toDateString(),
                'employment_type' => $vacancy?->employment_type ?? 'full_time',
                'status'          => $this->statuses->initialFor('hrm.employee'),
                'base_salary'     => $application->expected_salary,
            ]);

            $application->update([
                'employee_id'  => $employee->id,
                'converted_at' => now(),
            ]);

            return ['employee' => $employee, 'linkedExisting' => false, 'fresh' => true];
        });
    }

    public function bulkConvertToEmployee(array $ids): array
    {
        $converted     = 0;
        $alreadyLinked = [];
        $ineligible    = [];
        $missing       = [];
        $errors        = [];

        $apps = Application::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $app = $apps->get($id);
            if (! $app) { $missing[] = $id; continue; }
            if ($app->status !== 'hired') { $ineligible[] = $id; continue; }
            if ($app->employee_id)        { $alreadyLinked[] = $id; continue; }

            try {
                $this->convertToEmployee($app);
                $converted++;
            } catch (\Throwable $e) {
                $errors[] = ['id' => $id, 'message' => $e->getMessage()];
            }
        }

        return compact('converted', 'alreadyLinked', 'ineligible', 'missing', 'errors');
    }

    /**
     * Revert a recent (within 7 days) conversion: renames the employee_id
     * to free it for re-use, soft-deletes the employee, and clears the
     * application's link. Past the window the recruiter must use the
     * standard offboarding path (EmployeeService::terminateEmployee).
     */
    public function revertEmployeeConversion(Application $application): Application
    {
        if ($application->status !== 'hired') {
            throw new DomainException('Application is not in hired status.');
        }
        if (! $application->employee_id || ! $application->converted_at) {
            throw new DomainException('Application has no linked employee.');
        }

        $convertedAt = CarbonImmutable::parse($application->converted_at);
        if ($convertedAt->diffInDays(now()) > self::REVERT_CONVERSION_WINDOW_DAYS) {
            throw new DomainException('Revert window has expired. Use the offboarding flow instead.');
        }

        return DB::transaction(function () use ($application) {
            $employee = Employee::find($application->employee_id);
            if ($employee) {
                // Rename the employee_id so it falls out of generateNextEmployeeId()'s
                // scan and can be re-issued on a fresh convert. Preserve the original
                // inline for audit traceability.
                $employee->update([
                    'employee_id' => "{$employee->employee_id}-REV-" . Str::lower(Str::random(6)),
                ]);
                $employee->delete();
            }

            $application->update([
                'employee_id'  => null,
                'converted_at' => null,
            ]);

            return $application->refresh();
        });
    }

    /**
     * Auto-issue the next `<prefix>-<NNNN>` employee_id. Zero-indexed: first
     * issued id is `TT-0000`. Past `TT-9999`, width widens automatically.
     *
     * Soft-deleted (terminated) employees are INCLUDED in the max query —
     * the unique index on `employee_id` is full, not partial, so reused IDs
     * would collide on insert. Recycling a former employee's ID would also
     * confuse audit trails, so we just keep counting up.
     */
    public function generateNextEmployeeId(): string
    {
        $prefix = self::EMPLOYEE_ID_PREFIX;
        $pad    = self::EMPLOYEE_ID_PAD;

        $max = Employee::withTrashed()
            ->where('employee_id', 'like', "{$prefix}-%")
            ->selectRaw("MAX(CAST(SUBSTRING(employee_id FROM '^{$prefix}-(\\d+)$') AS INTEGER)) AS n")
            ->value('n');

        $next = is_null($max) ? 0 : ((int) $max) + 1;
        return $prefix . '-' . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }
}
