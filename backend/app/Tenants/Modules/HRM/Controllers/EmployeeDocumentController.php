<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\EmployeeDocument;
use Illuminate\Http\Request;

class EmployeeDocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeDocument::query()->orderByDesc('created_at');
        foreach (['employee_id', 'category'] as $f) {
            if ($request->filled($f)) $query->where($f, $request->string($f));
        }
        if ($request->boolean('expiring_soon')) {
            $query->whereNotNull('expires_at')->whereDate('expires_at', '<=', now()->addDays(30));
        }
        return response()->json(['data' => $query->paginate($request->integer('per_page', 25))]);
    }

    public function show(EmployeeDocument $employee_document)
    {
        return response()->json(['data' => $employee_document]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'title'       => 'required|string|max:200',
            'category'    => 'nullable|in:contract,id,certificate,other',
            'file_path'   => 'required|string|max:500',
            'mime_type'   => 'nullable|string|max:128',
            'size_bytes'  => 'nullable|integer|min:0',
            'issued_at'   => 'nullable|date',
            'expires_at'  => 'nullable|date|after_or_equal:issued_at',
        ]);
        $data['uploaded_by'] = $request->user()?->id;
        return response()->json(['success' => true, 'data' => EmployeeDocument::create($data)], 201);
    }

    public function destroy(EmployeeDocument $employee_document)
    {
        $employee_document->delete();
        return response()->json(['success' => true]);
    }
}
