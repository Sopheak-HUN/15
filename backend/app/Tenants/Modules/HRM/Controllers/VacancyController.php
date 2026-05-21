<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Vacancy;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Http\Request;

class VacancyController extends Controller
{
    public function __construct(protected WorkflowStatusService $statuses) {}

    public function index(Request $request)
    {
        $query = Vacancy::query()->with(['department:id,name', 'position:id,title']);
        foreach (['status', 'department_id', 'position_id'] as $f) {
            if ($request->filled($f)) $query->where($f, $request->string($f));
        }
        if ($request->filled('q')) {
            $query->where('title', 'ilike', '%' . $request->string('q') . '%');
        }
        return response()->json(['data' => $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25))]);
    }

    public function show(Vacancy $vacancy)
    {
        return response()->json(['data' => $vacancy->load(['department', 'position', 'hiringManager:id,first_name,last_name'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['status'] = $data['status'] ?? $this->statuses->initialFor('hrm.vacancy');
        return response()->json(['success' => true, 'data' => Vacancy::create($data)], 201);
    }

    public function update(Request $request, Vacancy $vacancy)
    {
        $data = $request->validate($this->rules($vacancy));
        if (isset($data['status']) && $data['status'] !== $vacancy->status) {
            try {
                $this->statuses->validateTransition('hrm.vacancy', $vacancy->status, $data['status']);
            } catch (DomainException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }
        $vacancy->update($data);
        return response()->json(['success' => true, 'data' => $vacancy->refresh()]);
    }

    public function destroy(Vacancy $vacancy)
    {
        $vacancy->delete();
        return response()->json(['success' => true]);
    }

    private function rules(?Vacancy $v = null): array
    {
        return [
            'title'             => ($v ? 'sometimes' : 'required') . '|string|max:200',
            'reference'         => ($v ? 'sometimes' : 'required') . '|string|max:32|unique:vacancies,reference' . ($v ? ',' . $v->id : ''),
            'department_id'     => 'nullable|uuid|exists:departments,id',
            'position_id'       => 'nullable|uuid|exists:positions,id',
            'description'       => 'nullable|string',
            'requirements'      => 'nullable|string',
            'location'          => 'nullable|string|max:120',
            'salary_min'        => 'nullable|numeric|min:0',
            'salary_max'        => 'nullable|numeric|gte:salary_min',
            'employment_type'   => 'nullable|in:full_time,part_time,contract,intern',
            'status'            => 'nullable|string|max:32',
            'opens_at'          => 'nullable|date',
            'closes_at'         => 'nullable|date|after_or_equal:opens_at',
            'hiring_manager_id' => 'nullable|uuid|exists:employees,id',
        ];
    }
}
