<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\LeaveBalance;
use App\Tenants\Modules\HRM\Models\LeaveRequest;
use App\Tenants\Modules\HRM\Models\LeaveType;
use App\Tenants\Modules\HRM\Services\LeaveService;
use DomainException;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function __construct(protected LeaveService $service) {}

    public function index(Request $request)
    {
        $query = LeaveRequest::query()
            ->with(['employee:id,first_name,last_name,employee_id', 'leaveType:id,name,code'])
            ->orderByDesc('created_at');

        foreach (['employee_id', 'leave_type_id', 'status'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->string($f));
            }
        }
        if ($request->filled('from')) $query->where('start_date', '>=', $request->string('from'));
        if ($request->filled('to'))   $query->where('end_date',   '<=', $request->string('to'));

        return response()->json(['data' => $query->paginate($request->integer('per_page', 25))]);
    }

    public function show(LeaveRequest $leave_request)
    {
        return response()->json(['data' => $leave_request->load(['employee', 'leaveType', 'approver:id,first_name,last_name'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'   => 'required|uuid|exists:employees,id',
            'leave_type_id' => 'required|uuid|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'days'          => 'nullable|numeric|min:0.5',
            'reason'        => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $type     = LeaveType::findOrFail($data['leave_type_id']);

        try {
            $req = $this->service->submitRequest($employee, $type, $data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $req->load(['employee', 'leaveType'])], 201);
    }

    public function approve(Request $request, LeaveRequest $leave_request)
    {
        $approver = $this->resolveApprover($request);
        if (! $approver) {
            return response()->json(['success' => false, 'message' => 'Approver employee not linked to caller.'], 422);
        }
        try {
            $updated = $this->service->approve($leave_request, $approver);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function reject(Request $request, LeaveRequest $leave_request)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        $approver = $this->resolveApprover($request);
        if (! $approver) {
            return response()->json(['success' => false, 'message' => 'Approver employee not linked to caller.'], 422);
        }
        try {
            $updated = $this->service->reject($leave_request, $approver, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function balances(string $employeeId)
    {
        $balances = LeaveBalance::with('leaveType:id,name,code,color')
            ->where('employee_id', $employeeId)
            ->orderByDesc('year')
            ->get();
        return response()->json(['data' => $balances]);
    }

    /**
     * The user resolves to an Employee row via users.id → employees.user_id.
     * Approvers must be linked; otherwise approval is rejected with a 422.
     */
    private function resolveApprover(Request $request): ?Employee
    {
        $userId = $request->user()?->id;
        if (! $userId) return null;
        return Employee::where('user_id', $userId)->first();
    }
}
