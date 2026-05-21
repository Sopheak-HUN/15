<?php

namespace App\Tenants\Modules\HRM\Services;

use App\Tenants\Modules\HRM\Models\Appraisal;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Support\Facades\DB;

class PerformanceService
{
    public function __construct(protected WorkflowStatusService $statuses) {}

    public function submit(Appraisal $appraisal, ?array $responses = null): Appraisal
    {
        return DB::transaction(function () use ($appraisal, $responses) {
            $this->statuses->validateTransition('hrm.appraisal', $appraisal->status, 'submitted');
            $appraisal->update([
                'status'       => 'submitted',
                'responses'    => $responses ?? $appraisal->responses,
                'submitted_at' => now(),
            ]);
            return $appraisal->refresh();
        });
    }

    public function review(Appraisal $appraisal, array $data): Appraisal
    {
        return DB::transaction(function () use ($appraisal, $data) {
            $this->statuses->validateTransition('hrm.appraisal', $appraisal->status, 'reviewed');
            $appraisal->update([
                'status'           => 'reviewed',
                'manager_comments' => $data['manager_comments'] ?? $appraisal->manager_comments,
                'overall_score'    => $data['overall_score']    ?? $appraisal->overall_score,
            ]);
            return $appraisal->refresh();
        });
    }

    public function close(Appraisal $appraisal): Appraisal
    {
        return DB::transaction(function () use ($appraisal) {
            $this->statuses->validateTransition('hrm.appraisal', $appraisal->status, 'closed');
            $appraisal->update(['status' => 'closed', 'closed_at' => now()]);
            return $appraisal->refresh();
        });
    }
}
