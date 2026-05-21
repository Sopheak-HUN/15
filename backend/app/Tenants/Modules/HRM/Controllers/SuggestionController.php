<?php

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\Suggestion;
use App\Tenants\Modules\HRM\Services\SuggestionService;
use DomainException;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function __construct(protected SuggestionService $service) {}

    public function index(Request $request)
    {
        $query = Suggestion::query()->with('reviewer:id,first_name,last_name')->orderByDesc('created_at');
        foreach (['status', 'category'] as $f) {
            if ($request->filled($f)) $query->where($f, $request->string($f));
        }
        return response()->json(['data' => $query->paginate($request->integer('per_page', 25))]);
    }

    public function show(Suggestion $suggestion)
    {
        return response()->json(['data' => $suggestion->load('reviewer:id,first_name,last_name')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'required|string|max:200',
            'body'         => 'required|string',
            'category'     => 'nullable|string|max:64',
            'is_anonymous' => 'boolean',
        ]);
        $submitter = $this->resolveSubmitter($request);
        $suggestion = $this->service->submit($submitter, $data);
        return response()->json(['success' => true, 'data' => $suggestion], 201);
    }

    public function transition(Request $request, Suggestion $suggestion)
    {
        $data = $request->validate([
            'action'   => 'required|in:acknowledge,action,dismiss',
            'response' => 'nullable|string|max:1000',
        ]);
        $reviewer = $this->resolveSubmitter($request);
        if (! $reviewer) {
            return response()->json(['success' => false, 'message' => 'Reviewer must be linked to an employee record.'], 422);
        }
        try {
            $method = $data['action'];
            $updated = $this->service->$method($suggestion, $reviewer, $data['response'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function destroy(Suggestion $suggestion)
    {
        $suggestion->delete();
        return response()->json(['success' => true]);
    }

    private function resolveSubmitter(Request $request): ?Employee
    {
        $userId = $request->user()?->id;
        return $userId ? Employee::where('user_id', $userId)->first() : null;
    }
}
