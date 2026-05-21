<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\PayrollPeriod;
use App\Tenants\Modules\HRM\Services\PayrollService;
use DomainException;
use Illuminate\Http\Request;

class PayrollPeriodController extends Controller
{
    public function __construct(protected PayrollService $service) {}

    public function index(Request $request)
    {
        $query = PayrollPeriod::query()->orderByDesc('start_date');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        return response()->json(['data' => $query->paginate($request->integer('per_page', 25))]);
    }

    public function show(PayrollPeriod $payroll_period)
    {
        return response()->json(['data' => $payroll_period->load('payslips:id,payroll_period_id,employee_id,net_pay,currency')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'label'      => 'nullable|string|max:80',
        ]);
        try {
            $period = $this->service->createPeriod($data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $period], 201);
    }

    public function process(Request $request, PayrollPeriod $payroll_period)
    {
        try {
            $period = $this->service->processPeriod($payroll_period, $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $period->load('payslips')]);
    }
}
