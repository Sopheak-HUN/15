<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Application;
use App\Tenants\Modules\HRM\Services\RecruitmentService;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        protected RecruitmentService $service,
        protected WorkflowStatusService $statuses,
    ) {}

    public function index(Request $request)
    {
        $query = Application::query()->with(['vacancy:id,title,reference', 'employee:id,employee_id,first_name,last_name']);
        foreach (['status', 'vacancy_id'] as $f) {
            if ($request->filled($f)) $query->where($f, $request->string($f));
        }
        if ($request->filled('q')) {
            $term = '%' . $request->string('q') . '%';
            $query->where(fn ($q) =>
                $q->where('first_name', 'ilike', $term)
                  ->orWhere('last_name', 'ilike', $term)
                  ->orWhere('email', 'ilike', $term)
            );
        }
        return response()->json(['data' => $query->orderByDesc('created_at')->paginate($request->integer('per_page', 25))]);
    }

    public function show(Application $application)
    {
        return response()->json(['data' => $application->load(['vacancy', 'employee', 'interviews.feedbacks'])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vacancy_id'        => 'required|uuid|exists:vacancies,id',
            'first_name'        => 'required|string|max:80',
            'last_name'         => 'required|string|max:80',
            'email'             => 'required|email|max:160',
            'phone'             => 'nullable|string|max:32',
            'resume_path'       => 'nullable|string|max:255',
            'cover_letter_path' => 'nullable|string|max:255',
            'expected_salary'   => 'nullable|numeric|min:0',
        ]);
        $data['status'] = $this->statuses->initialFor('hrm.application');
        return response()->json(['success' => true, 'data' => Application::create($data)], 201);
    }

    public function transition(Request $request, Application $application)
    {
        $data = $request->validate(['status' => 'required|string|max:32']);
        try {
            $updated = $this->service->transitionApplication($application, $data['status']);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function convertToEmployee(Application $application)
    {
        try {
            $result = $this->service->convertToEmployee($application);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json([
            'success'        => true,
            'data'           => $result['employee'],
            'linkedExisting' => $result['linkedExisting'],
            'fresh'          => $result['fresh'],
        ]);
    }

    public function bulkConvert(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'uuid',
        ]);
        $result = $this->service->bulkConvertToEmployee($data['ids']);
        return response()->json(array_merge(['success' => true], $result));
    }

    public function revertConversion(Application $application)
    {
        try {
            $updated = $this->service->revertEmployeeConversion($application);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function destroy(Application $application)
    {
        $application->delete();
        return response()->json(['success' => true]);
    }
}
