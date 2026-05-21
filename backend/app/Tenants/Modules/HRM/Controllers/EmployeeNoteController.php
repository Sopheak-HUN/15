<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\EmployeeNote;
use Illuminate\Http\Request;

class EmployeeNoteController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeNote::query()->with('author:id,first_name,last_name')->orderByDesc('created_at');
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->string('employee_id'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        return response()->json(['data' => $query->paginate($request->integer('per_page', 25))]);
    }

    public function show(EmployeeNote $employee_note)
    {
        return response()->json(['data' => $employee_note->load('author:id,first_name,last_name')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'     => 'required|uuid|exists:employees,id',
            'category'        => 'nullable|in:general,performance,disciplinary,praise',
            'title'           => 'nullable|string|max:200',
            'body'            => 'required|string',
            'is_private'      => 'boolean',
            'is_disciplinary' => 'boolean',
            'incident_date'   => 'nullable|date',
        ]);
        $author = $request->user() ? Employee::where('user_id', $request->user()->id)->first() : null;
        $data['author_id'] = $author?->id;
        return response()->json(['success' => true, 'data' => EmployeeNote::create($data)], 201);
    }

    public function update(Request $request, EmployeeNote $employee_note)
    {
        $data = $request->validate([
            'category'        => 'sometimes|in:general,performance,disciplinary,praise',
            'title'           => 'nullable|string|max:200',
            'body'            => 'sometimes|string',
            'is_private'      => 'boolean',
            'is_disciplinary' => 'boolean',
            'incident_date'   => 'nullable|date',
        ]);
        $employee_note->update($data);
        return response()->json(['success' => true, 'data' => $employee_note->refresh()]);
    }

    public function destroy(EmployeeNote $employee_note)
    {
        $employee_note->delete();
        return response()->json(['success' => true]);
    }
}
