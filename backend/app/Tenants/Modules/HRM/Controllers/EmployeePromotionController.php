<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\EmployeePromotion;
use App\Tenants\Modules\HRM\Services\EmployeeService;
use Illuminate\Http\Request;

class EmployeePromotionController extends Controller
{
    public function __construct(protected EmployeeService $service) {}

    /**
     * List career journal entries for one employee, newest first.
     * Eager-loads the position/department/approver relations so the
     * timeline renders without follow-up queries.
     */
    public function index(string $employeeId)
    {
        Employee::findOrFail($employeeId);

        $rows = EmployeePromotion::with([
            'previousPosition:id,title',
            'newPosition:id,title',
            'previousDepartment:id,name',
            'newDepartment:id,name',
            'approver:id,first_name,last_name,employee_id',
        ])
            ->where('employee_id', $employeeId)
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function store(Request $request, string $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $data = $request->validate([
            'effective_date'         => 'required|date',
            'type'                   => 'required|in:promotion,lateral,demotion,salary_adjustment',
            // Position / department targets — at least one of (position,
            // role_name, salary) should change, but we don't enforce that
            // here so HR can also use this table for organizational
            // restructurings where only the department changes.
            'new_position_id'        => 'nullable|uuid|exists:positions,id',
            'new_department_id'      => 'nullable|uuid|exists:departments,id',
            'new_role_name'          => 'nullable|string|max:120',
            'new_salary'             => 'nullable|numeric|min:0',
            'currency'               => 'nullable|string|size:3',
            'reason'                 => 'nullable|string|max:2000',
            'approved_by'            => 'nullable|uuid|exists:employees,id',
            // Defaults to true — recording a promotion usually means it
            // takes effect now. Set false when entering historical data.
            'apply_now'              => 'nullable|boolean',
        ]);

        $promotion = $this->service->recordPromotion($employee, $data);

        return response()->json(['success' => true, 'data' => $promotion], 201);
    }

    public function destroy(string $employeeId, EmployeePromotion $promotion)
    {
        // Make sure the route's promotion id actually belongs to the
        // employee in the URL — defends against forged ids that walk a
        // different employee's history.
        abort_unless($promotion->employee_id === $employeeId, 404);
        $promotion->delete();
        return response()->json(['success' => true]);
    }
}
