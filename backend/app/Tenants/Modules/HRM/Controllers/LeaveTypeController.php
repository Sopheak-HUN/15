<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function index()
    {
        return response()->json(['data' => LeaveType::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        return response()->json(['success' => true, 'data' => LeaveType::create($data)], 201);
    }

    public function update(Request $request, LeaveType $leave_type)
    {
        $data = $request->validate($this->rules($leave_type));
        $leave_type->update($data);
        return response()->json(['success' => true, 'data' => $leave_type->refresh()]);
    }

    public function destroy(LeaveType $leave_type)
    {
        $leave_type->delete();
        return response()->json(['success' => true]);
    }

    private function rules(?LeaveType $type = null): array
    {
        return [
            'name'              => 'required|string|max:80',
            'code'              => 'required|string|max:32|unique:leave_types,code' . ($type ? ',' . $type->id : ''),
            'default_balance'   => 'nullable|numeric|min:0',
            'is_paid'           => 'boolean',
            'accrues'           => 'boolean',
            'requires_approval' => 'boolean',
            'color'             => 'nullable|string|max:32',
        ];
    }
}
