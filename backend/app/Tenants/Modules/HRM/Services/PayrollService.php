<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\EmployeePayComponent;
use App\Tenants\Modules\HRM\Models\PayComponent;
use App\Tenants\Modules\HRM\Models\PayrollPeriod;
use App\Tenants\Modules\HRM\Models\Payslip;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Salary engine. Resolves earnings + deductions for each active employee in
 * a payroll period, applies fixed/percentage calculations, persists one
 * Payslip row per employee, and locks the period when done.
 *
 * Tax engine is intentionally minimal here — production tenants plug in
 * jurisdiction-specific rules via PayComponent rows (e.g. a percentage_of_base
 * deduction labelled "NSSF" at 4%).
 */
class PayrollService
{
    public function __construct(protected WorkflowStatusService $statuses) {}

    public function createPeriod(array $data): PayrollPeriod
    {
        $start = CarbonImmutable::parse($data['start_date']);
        $end   = CarbonImmutable::parse($data['end_date']);
        if ($end->lt($start)) {
            throw new DomainException('Payroll period end must be on or after start.');
        }
        return PayrollPeriod::create([
            'start_date' => $start,
            'end_date'   => $end,
            'label'      => $data['label'] ?? $start->format('F Y'),
            'status'     => $this->statuses->initialFor('hrm.payroll_period'),
        ]);
    }

    /**
     * Run the payroll engine for every active employee. Idempotent: if a
     * payslip already exists for (period, employee), it's recomputed.
     */
    public function processPeriod(PayrollPeriod $period, ?int $processedBy = null): PayrollPeriod
    {
        if ($period->status === 'closed') {
            throw new DomainException('Payroll period is already closed.');
        }

        DB::transaction(function () use ($period, $processedBy) {
            $globalComponents = PayComponent::where('is_active', true)->get();
            $employees = Employee::where('status', 'active')->get();

            foreach ($employees as $employee) {
                $this->computePayslip($period, $employee, $globalComponents);
            }

            $period->update([
                'status'       => 'closed',  // initial is draft, terminal closed per the spec
                'processed_at' => now(),
                'processed_by' => $processedBy,
            ]);
        });

        return $period->refresh();
    }

    /**
     * Compute a single employee's payslip. Pulls global PayComponent rows
     * plus any employee-specific overrides (effective in the period).
     */
    private function computePayslip(PayrollPeriod $period, Employee $employee, Collection $globalComponents): Payslip
    {
        $base = (float) ($employee->base_salary ?: 0);

        $overrides = EmployeePayComponent::with('component')
            ->where('employee_id', $employee->id)
            ->where(function ($q) use ($period) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $period->end_date);
            })
            ->where(function ($q) use ($period) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $period->start_date);
            })
            ->get()
            ->keyBy('pay_component_id');

        $earnings = ['base_salary' => $base];
        $deductions = [];
        $items = [['code' => 'BASE', 'name' => 'Base salary', 'amount' => $base, 'kind' => 'earning']];

        foreach ($globalComponents as $component) {
            $amount = $this->resolveAmount($component, $overrides->get($component->id), $base);
            $items[] = [
                'code'   => $component->code,
                'name'   => $component->name,
                'kind'   => $component->kind,
                'amount' => $amount,
            ];
            if ($component->kind === 'earning') {
                $earnings[$component->code] = $amount;
            } else {
                $deductions[$component->code] = $amount;
            }
        }

        $grossEarnings   = array_sum($earnings);
        $totalDeductions = array_sum($deductions);
        $net             = $grossEarnings - $totalDeductions;

        return Payslip::updateOrCreate(
            ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
            [
                'gross_earnings'   => $grossEarnings,
                'total_deductions' => $totalDeductions,
                'net_pay'          => $net,
                'currency'         => $employee->currency ?? 'USD',
                'line_items'       => $items,
                'issued_at'        => now(),
            ]
        );
    }

    private function resolveAmount(PayComponent $component, ?EmployeePayComponent $override, float $base): float
    {
        $amount = (float) ($override?->override_amount ?? $component->amount);
        return $component->calculation === 'percentage_of_base'
            ? round(($amount / 100.0) * $base, 2)
            : round($amount, 2);
    }
}
