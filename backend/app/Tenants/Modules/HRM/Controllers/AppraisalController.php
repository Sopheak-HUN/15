<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Appraisal;
use App\Tenants\Modules\HRM\Services\PerformanceService;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Http\Request;

class AppraisalController extends Controller
{
    public function __construct(
        protected PerformanceService $service,
        protected WorkflowStatusService $statuses,
    ) {}

    public function index(Request $request)
    {
        $query = Appraisal::with(['cycle:id,name', 'employee:id,first_name,last_name', 'reviewer:id,first_name,last_name']);
        foreach (['cycle_id', 'employee_id', 'status'] as $f) {
            if ($request->filled($f)) $query->where($f, $request->string($f));
        }
        return response()->json(['data' => $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25))]);
    }

    public function show(Appraisal $appraisal)
    {
        return response()->json(['data' => $appraisal->load(['cycle', 'employee', 'reviewer'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cycle_id'         => 'required|uuid|exists:appraisal_cycles,id',
            'employee_id'      => 'required|uuid|exists:employees,id',
            'reviewer_id'      => 'nullable|uuid|exists:employees,id',
            'responses'        => 'nullable|array',
            'employee_comments' => 'nullable|string',
        ]);
        $data['status'] = $this->statuses->initialFor('hrm.appraisal');
        return response()->json(['success' => true, 'data' => Appraisal::create($data)], 201);
    }

    public function submit(Request $request, Appraisal $appraisal)
    {
        $responses = $request->input('responses');
        try {
            $updated = $this->service->submit($appraisal, $responses);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function review(Request $request, Appraisal $appraisal)
    {
        $data = $request->validate([
            'manager_comments' => 'nullable|string',
            'overall_score'    => 'nullable|numeric|min:0|max:100',
        ]);
        try {
            $updated = $this->service->review($appraisal, $data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function close(Appraisal $appraisal)
    {
        try {
            $updated = $this->service->close($appraisal);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }
}
