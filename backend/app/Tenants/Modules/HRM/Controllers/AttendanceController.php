<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Attendance;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Services\AttendanceService;
use DomainException;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $service) {}

    public function index(Request $request)
    {
        $query = Attendance::query()->with('employee:id,first_name,last_name,employee_id');

        // Auto-scope: regular employees (no hrm.employee.read) can only
        // see their own attendance. The query string filter is honored
        // for admins/HR; for everyone else it's overridden with the
        // caller's own employee id so no one peeks at coworkers.
        $scope = $this->selfScopedEmployeeId($request);
        if ($scope !== false) {
            $query->where('employee_id', $scope);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->string('employee_id'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->string('start_date'), $request->string('end_date')]);
        } elseif ($request->filled('date')) {
            $query->where('date', $request->string('date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $attendances = $query->orderByDesc('date')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json(['data' => $attendances]);
    }

    public function show(Request $request, Attendance $attendance)
    {
        // Block staff from peeking at someone else's record by id.
        $scope = $this->selfScopedEmployeeId($request);
        if ($scope !== false && $attendance->employee_id !== $scope) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }
        return response()->json(['data' => $attendance->load('employee')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'date'        => 'required|date',
            'status'      => 'required|in:present,late,absent,half_day,on_leave',
            'check_in'    => 'nullable|date',
            'check_out'   => 'nullable|date|after_or_equal:check_in',
            'notes'       => 'nullable|string|max:1000',
        ]);

        try {
            $attendance = $this->service->logAttendance(
                $data['employee_id'],
                $data['date'],
                $data['status'],
                $data['check_in'] ?? null,
                $data['check_out'] ?? null,
                $data['notes'] ?? null
            );
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $attendance], 201);
    }

    public function update(Request $request, Attendance $attendance)
    {
        $data = $request->validate([
            'status'    => 'sometimes|required|in:present,late,absent,half_day,on_leave',
            'check_in'  => 'nullable|date',
            'check_out' => 'nullable|date|after_or_equal:check_in',
            'notes'     => 'nullable|string|max:1000',
        ]);

        try {
            $attendance = $this->service->logAttendance(
                $attendance->employee_id,
                $attendance->date->toDateString(),
                $data['status'] ?? $attendance->status,
                $request->has('check_in') ? $data['check_in'] : ($attendance->check_in ? $attendance->check_in->toDateTimeString() : null),
                $request->has('check_out') ? $data['check_out'] : ($attendance->check_out ? $attendance->check_out->toDateTimeString() : null),
                $request->has('notes') ? $data['notes'] : $attendance->notes
            );
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return response()->json(['success' => true]);
    }

    public function checkIn(Request $request)
    {
        $employee = $this->resolveEmployee($request);
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with an employee record.',
            ], 422);
        }

        try {
            $attendance = $this->service->checkIn($employee, $request->input('notes'));
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function checkOut(Request $request)
    {
        $employee = $this->resolveEmployee($request);
        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'User is not associated with an employee record.',
            ], 422);
        }

        try {
            $attendance = $this->service->checkOut($employee, $request->input('notes'));
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function breakOut(Request $request)
    {
        $employee = $this->resolveEmployee($request);
        if (! $employee) {
            return response()->json(['success' => false, 'message' => 'User is not associated with an employee record.'], 422);
        }
        try {
            $attendance = $this->service->breakOut($employee, $request->input('notes'));
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function breakIn(Request $request)
    {
        $employee = $this->resolveEmployee($request);
        if (! $employee) {
            return response()->json(['success' => false, 'message' => 'User is not associated with an employee record.'], 422);
        }
        try {
            $attendance = $this->service->breakIn($employee, $request->input('notes'));
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $attendance]);
    }

    public function stats(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
        ]);

        // Staff users can only request stats for themselves. If they
        // pass someone else's id, return 403 rather than silently
        // showing the wrong person's stats.
        $scope = $this->selfScopedEmployeeId($request);
        $requested = $request->string('employee_id');
        if ($scope !== false && (string) $requested !== $scope) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $stats = $this->service->getStats(
            $requested,
            $request->string('start_date'),
            $request->string('end_date')
        );

        return response()->json(['data' => $stats]);
    }

    protected function resolveEmployee(Request $request): ?Employee
    {
        $userId = $request->user()?->id;
        return $userId ? Employee::where('user_id', $userId)->first() : null;
    }

    /**
     * Return the employee_id queries should be locked to, or `false`
     * when the caller is admin/HR (hrm.employee.read) and should see
     * everyone. Mirrors LeaveRequestController::selfScopedEmployeeId so
     * the two modules stay consistent — extract to a trait if a third
     * module adopts the pattern.
     *
     * Returns a sentinel UUID for staff with no linked employee so
     * queries match zero rows instead of accidentally returning all.
     *
     * @return string|false
     */
    private function selfScopedEmployeeId(Request $request)
    {
        $user = $request->user();
        if (! $user) return '00000000-0000-0000-0000-000000000000';

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
