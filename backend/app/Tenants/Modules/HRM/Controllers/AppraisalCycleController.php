<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\AppraisalCycle;
use Illuminate\Http\Request;

class AppraisalCycleController extends Controller
{
    public function index()
    {
        return response()->json(['data' => AppraisalCycle::orderByDesc('start_date')->get()]);
    }

    public function show(AppraisalCycle $appraisal_cycle)
    {
        return response()->json(['data' => $appraisal_cycle->load('appraisals:id,cycle_id,employee_id,status,overall_score')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:120',
            'start_date'   => 'required|date',
            'end_date'     => 'required|date|after_or_equal:start_date',
            'rating_scale' => 'nullable|array',
            'is_active'    => 'boolean',
        ]);
        return response()->json(['success' => true, 'data' => AppraisalCycle::create($data)], 201);
    }

    public function update(Request $request, AppraisalCycle $appraisal_cycle)
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:120',
            'start_date'   => 'sometimes|date',
            'end_date'     => 'sometimes|date|after_or_equal:start_date',
            'rating_scale' => 'nullable|array',
            'is_active'    => 'boolean',
        ]);
        $appraisal_cycle->update($data);
        return response()->json(['success' => true, 'data' => $appraisal_cycle->refresh()]);
    }

    public function destroy(AppraisalCycle $appraisal_cycle)
    {
        $appraisal_cycle->delete();
        return response()->json(['success' => true]);
    }
}
