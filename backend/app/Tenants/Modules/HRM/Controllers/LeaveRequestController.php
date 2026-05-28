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

        // Auto-scope: a regular employee (no hrm.employee.read perm) only
        // ever sees their own requests, regardless of what employee_id
        // they pass in the query string. Admins/HR keep the existing
        // query-string filter unchanged.
        $scope = $this->selfScopedEmployeeId($request);
        if ($scope !== false) {
            $query->where('employee_id', $scope);
        } else {
            foreach (['employee_id', 'leave_type_id', 'status'] as $f) {
                if ($request->filled($f)) {
                    $query->where($f, $request->string($f));
                }
            }
        }
        // leave_type_id + status filters still apply for self-scoped
        // users so the staff dashboard can filter their own history.
        if ($scope !== false) {
            foreach (['leave_type_id', 'status'] as $f) {
                if ($request->filled($f)) $query->where($f, $request->string($f));
            }
        }
        if ($request->filled('from')) $query->where('start_date', '>=', $request->string('from'));
        if ($request->filled('to'))   $query->where('end_date',   '<=', $request->string('to'));

        return response()->json(['data' => $query->paginate($request->integer('per_page', 25))]);
    }

    public function show(Request $request, LeaveRequest $leave_request)
    {
        // Block staff from peeking at someone else's leave by guessing the
        // request UUID — auto-scope check matches the index() pattern.
        $scope = $this->selfScopedEmployeeId($request);
        if ($scope !== false && $leave_request->employee_id !== $scope) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }
        return response()->json(['data' => $leave_request->load([
            'employee', 'leaveType',
            'approver:id,first_name,last_name',
            'assignedTo:id,first_name,last_name,employee_id',
        ])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'    => 'required|uuid|exists:employees,id',
            'leave_type_id'  => 'required|uuid|exists:leave_types,id',
            'duration_type'  => 'nullable|in:full_day,half_day',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'days'           => 'nullable|numeric|min:0.5',
            'reason'         => 'nullable|string|max:500',
            'assign_to'      => 'nullable|uuid|exists:employees,id',
            // Reference file uploaded via POST /api/uploads/leave-reference.
            // Must live under the tenant's per-employee prefix to block
            // forged-key cross-tenant copies.
            'reference_path' => 'nullable|string|max:255|starts_with:tenants/',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $type     = LeaveType::findOrFail($data['leave_type_id']);

        try {
            $req = $this->service->submitRequest($employee, $type, $data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $req->load([
            'employee', 'leaveType', 'assignedTo:id,first_name,last_name,employee_id',
        ])], 201);
    }

    public function approve(Request $request, LeaveRequest $leave_request)
    {
        // Approver lookup is best-effort: if the caller is linked to an
        // Employee row we stamp `approved_by` for HR reporting; otherwise
        // (HR admin / super-admin with no Employee profile) we pass null.
        // The Auditable trait still records `user_id` on the change so
        // there's a full who-did-this trail in `audit_logs`.
        $approver = $this->resolveApprover($request);
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
        try {
            $updated = $this->service->reject($leave_request, $approver, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function balances(Request $request, string $employeeId)
    {
        // Self-scoped users can only fetch their own balances.
        $scope = $this->selfScopedEmployeeId($request);
        if ($scope !== false && $employeeId !== $scope) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }
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

    /**
     * Return the employee_id we should scope queries to, or `false` when
     * the caller is an admin/HR user (`hrm.employee.read`) and should see
     * all rows. A `null` return means "self-scoped, but no linked employee"
     * — which we treat as scoping to a sentinel UUID that can't match
     * anything, so the user sees an empty list rather than everyone's data.
     *
     * Why `false` for the unscoped case: distinguishes "no scope needed"
     * from "scope to nothing", which `null` would conflate.
     *
     * @return string|false
     */
    private function selfScopedEmployeeId(Request $request)
    {
        $user = $request->user();
        if (! $user) return '00000000-0000-0000-0000-000000000000';

        // Admins / HR with employee.read see everything.
        $user->loadMissing('role.permissions:id,name');
        if ($user->role?->name === 'super-admin') return false;
        $perms = $user->role
            ? $user->role->effectivePermissions()->pluck('name')->all()
            : [];
        if (in_array('hrm.employee.read', $perms, true)) return false;

        $emp = Employee::where('user_id', $user->id)->first(['id']);
        return $emp?->id ?? '00000000-0000-0000-0000-000000000000';
    }
}
