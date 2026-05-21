<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Interview;
use App\Tenants\Modules\HRM\Models\InterviewFeedback;
use Illuminate\Http\Request;

class InterviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Interview::query()->with(['application:id,first_name,last_name,vacancy_id']);
        if ($request->filled('application_id')) {
            $query->where('application_id', $request->string('application_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        return response()->json(['data' => $query->orderBy('scheduled_at')->paginate($request->integer('per_page', 25))]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'application_id'    => 'required|uuid|exists:applications,id',
            'scheduled_at'      => 'required|date',
            'duration_minutes'  => 'nullable|integer|min:5|max:480',
            'mode'              => 'nullable|in:virtual,onsite,phone',
            'location'          => 'nullable|string|max:255',
            'round_label'       => 'nullable|string|max:80',
            'status'            => 'nullable|in:scheduled,completed,cancelled',
        ]);
        return response()->json(['success' => true, 'data' => Interview::create($data)], 201);
    }

    public function update(Request $request, Interview $interview)
    {
        $data = $request->validate([
            'scheduled_at'      => 'sometimes|date',
            'duration_minutes'  => 'nullable|integer|min:5|max:480',
            'mode'              => 'nullable|in:virtual,onsite,phone',
            'location'          => 'nullable|string|max:255',
            'round_label'       => 'nullable|string|max:80',
            'status'            => 'nullable|in:scheduled,completed,cancelled',
        ]);
        $interview->update($data);
        return response()->json(['success' => true, 'data' => $interview->refresh()]);
    }

    public function destroy(Interview $interview)
    {
        $interview->delete();
        return response()->json(['success' => true]);
    }

    public function storeFeedback(Request $request, Interview $interview)
    {
        $data = $request->validate([
            'reviewer_id'    => 'nullable|uuid|exists:employees,id',
            'rating'         => 'nullable|integer|min:1|max:5',
            'recommendation' => 'nullable|in:hire,reject,hold',
            'strengths'      => 'nullable|string',
            'weaknesses'     => 'nullable|string',
            'notes'          => 'nullable|string',
        ]);
        $data['interview_id'] = $interview->id;
        return response()->json(['success' => true, 'data' => InterviewFeedback::create($data)], 201);
    }
}
