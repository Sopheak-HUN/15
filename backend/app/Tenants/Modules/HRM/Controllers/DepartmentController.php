<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::query()
            ->with(['parent:id,name', 'manager:id,first_name,last_name']);

        if ($request->boolean('include_inactive') !== true) {
            $query->where('is_active', true);
        }
        if ($request->filled('q')) {
            $term = '%' . $request->string('q') . '%';
            $query->where(fn ($q) => $q->where('name', 'ilike', $term)->orWhere('code', 'ilike', $term));
        }

        return response()->json(['data' => $query->orderBy('name')->paginate($request->integer('per_page', 25))]);
    }

    public function show(Department $department)
    {
        return response()->json(['data' => $department->load(['parent', 'manager', 'children'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'code'        => 'required|string|max:32|unique:departments,code',
            'parent_id'   => 'nullable|uuid|exists:departments,id',
            'manager_id'  => 'nullable|uuid|exists:employees,id',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);
        return response()->json(['success' => true, 'data' => Department::create($data)], 201);
    }

    public function update(Request $request, Department $department)
    {
        $data = $request->validate([
            'name'        => 'sometimes|string|max:120',
            'code'        => 'sometimes|string|max:32|unique:departments,code,' . $department->id,
            'parent_id'   => 'nullable|uuid|exists:departments,id|not_in:' . $department->id,
            'manager_id'  => 'nullable|uuid|exists:employees,id',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);
        $department->update($data);
        return response()->json(['success' => true, 'data' => $department->refresh()]);
    }

    public function destroy(Department $department)
    {
        $department->delete();
        return response()->json(['success' => true]);
    }
}
