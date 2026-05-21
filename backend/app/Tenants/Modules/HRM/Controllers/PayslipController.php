<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Payslip;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $query = Payslip::with(['employee:id,first_name,last_name,employee_id', 'period:id,label,start_date,end_date']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->string('employee_id'));
        }
        if ($request->filled('period_id')) {
            $query->where('payroll_period_id', $request->string('period_id'));
        }

        return response()->json(['data' => $query->orderByDesc('issued_at')->paginate($request->integer('per_page', 25))]);
    }

    public function show(Payslip $payslip)
    {
        return response()->json(['data' => $payslip->load(['employee', 'period'])]);
    }
}
