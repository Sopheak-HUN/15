<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Tenants\Modules\HRM\Models\Employee;
use App\Tenants\Modules\HRM\Models\Suggestion;
use DomainException;

class SuggestionService
{
    public function submit(?Employee $submitter, array $data): Suggestion
    {
        $isAnonymous = (bool) ($data['is_anonymous'] ?? false);
        return Suggestion::create([
            'employee_id'  => $isAnonymous ? null : $submitter?->id,
            'category'     => $data['category'] ?? 'general',
            'title'        => $data['title'],
            'body'         => $data['body'],
            'is_anonymous' => $isAnonymous,
            'status'       => 'new',
        ]);
    }

    public function acknowledge(Suggestion $suggestion, Employee $reviewer, ?string $response = null): Suggestion
    {
        return $this->transition($suggestion, 'acknowledged', $reviewer, $response);
    }

    public function action(Suggestion $suggestion, Employee $reviewer, ?string $response = null): Suggestion
    {
        return $this->transition($suggestion, 'actioned', $reviewer, $response);
    }

    public function dismiss(Suggestion $suggestion, Employee $reviewer, ?string $response = null): Suggestion
    {
        return $this->transition($suggestion, 'dismissed', $reviewer, $response);
    }

    private function transition(Suggestion $suggestion, string $status, Employee $reviewer, ?string $response): Suggestion
    {
        $allowed = ['new' => ['acknowledged', 'dismissed'],
                    'acknowledged' => ['actioned', 'dismissed'],
                    'actioned' => [],
                    'dismissed' => []];
        if (! in_array($status, $allowed[$suggestion->status] ?? [], true)) {
            throw new DomainException("Cannot transition from {$suggestion->status} to {$status}.");
        }
        $suggestion->update([
            'status'      => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'response'    => $response ?? $suggestion->response,
        ]);
        return $suggestion->refresh();
    }
}
