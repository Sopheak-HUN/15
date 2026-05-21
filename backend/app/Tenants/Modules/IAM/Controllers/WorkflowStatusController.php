<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\IAM\Models\WorkflowStatus;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Illuminate\Http\Request;

class WorkflowStatusController extends Controller
{
    public function __construct(protected WorkflowStatusService $statuses) {}

    public function index(Request $request)
    {
        $query = WorkflowStatus::query()->orderBy('module')->orderBy('sort_order');
        if ($request->filled('module')) {
            $query->where('module', $request->string('module'));
        }
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request, null);
        $row  = WorkflowStatus::create($data);
        $this->statuses->flushCache();
        return response()->json(['success' => true, 'data' => $row], 201);
    }

    public function update(Request $request, WorkflowStatus $status)
    {
        $data = $this->validatePayload($request, $status->id);
        $status->update($data);
        $this->statuses->flushCache();
        return response()->json(['success' => true, 'data' => $status]);
    }

    public function destroy(WorkflowStatus $status)
    {
        $status->delete();
        $this->statuses->flushCache();
        return response()->json(['success' => true]);
    }

    private function validatePayload(Request $request, ?string $id): array
    {
        $uniqueKey = $id
            ? "unique:workflow_statuses,key,{$id},id,module," . $request->input('module', '')
            : 'unique:workflow_statuses,NULL,id,module,' . $request->input('module', '');

        return $request->validate([
            'module'              => 'required|string|max:64',
            'key'                 => "required|string|max:64|{$uniqueKey}",
            'label'               => 'required|string|max:120',
            'color'               => 'nullable|string|max:32',
            'icon'                => 'nullable|string|max:64',
            'is_initial'          => 'boolean',
            'is_terminal'         => 'boolean',
            'allowed_transitions' => 'nullable|array',
            'allowed_transitions.*' => 'string|max:64',
            'sort_order'          => 'nullable|integer|min:0',
        ]);
    }
}
