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

        if ($request->filled('employee_id')) {
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

    public function show(Attendance $attendance)
    {
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

    public function stats(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'start_date'  => 'required|date',
            'end_date'    => 'required|date|after_or_equal:start_date',
        ]);

        $stats = $this->service->getStats(
            $request->string('employee_id'),
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
}
