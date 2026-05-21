<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    public function index(Request $request)
    {
        $query = Position::query()->with('department:id,name');
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->string('department_id'));
        }
        if ($request->boolean('include_inactive') !== true) {
            $query->where('is_active', true);
        }
        return response()->json(['data' => $query->orderBy('title')->paginate($request->integer('per_page', 25))]);
    }

    public function show(Position $position)
    {
        return response()->json(['data' => $position->load('department')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:120',
            'code'          => 'required|string|max:32|unique:positions,code',
            'department_id' => 'nullable|uuid|exists:departments,id',
            'min_salary'    => 'nullable|numeric|min:0',
            'max_salary'    => 'nullable|numeric|gte:min_salary',
            'is_active'     => 'boolean',
        ]);
        return response()->json(['success' => true, 'data' => Position::create($data)], 201);
    }

    public function update(Request $request, Position $position)
    {
        $data = $request->validate([
            'title'         => 'sometimes|string|max:120',
            'code'          => 'sometimes|string|max:32|unique:positions,code,' . $position->id,
            'department_id' => 'nullable|uuid|exists:departments,id',
            'min_salary'    => 'nullable|numeric|min:0',
            'max_salary'    => 'nullable|numeric|gte:min_salary',
            'is_active'     => 'boolean',
        ]);
        $position->update($data);
        return response()->json(['success' => true, 'data' => $position->refresh()]);
    }

    public function destroy(Position $position)
    {
        $position->delete();
        return response()->json(['success' => true]);
    }
}
