<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Tenants\Modules\IAM\Models\WorkflowStatus;
use DomainException;
use Illuminate\Support\Collection;

/**
 * Resolves and validates workflow statuses for any tenant-scoped module
 * that has a state machine (per skills/hrm/rules.md). Cached per request
 * to avoid hammering the DB on hot paths like payroll batch processing.
 */
class WorkflowStatusService
{
    /** @var array<string, Collection<int, WorkflowStatus>> */
    private array $cache = [];

    /**
     * Bootstrap status for a new record of the given module key.
     * Throws if no initial status is configured — that's a seeding bug.
     */
    public function initialFor(string $module): string
    {
        $status = $this->all($module)->firstWhere('is_initial', true);
        if (! $status) {
            throw new DomainException("No initial status configured for module '{$module}'.");
        }
        return $status->key;
    }

    /**
     * Validate a transition. Throws DomainException so the controller can
     * surface a 422 cleanly. Empty allowed_transitions means "any" — useful
     * for the very first record before admins customize the flow.
     */
    public function validateTransition(string $module, string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $current = $this->lookup($module, $from);
        if (! $current) {
            throw new DomainException("Status '{$from}' is unknown for module '{$module}'.");
        }
        if ($current->is_terminal) {
            throw new DomainException("Cannot transition from terminal status '{$from}'.");
        }

        $allowed = $current->allowed_transitions ?? [];
        if (! empty($allowed) && ! in_array($to, $allowed, true)) {
            throw new DomainException(
                "Transition '{$from}' → '{$to}' is not allowed for module '{$module}'."
            );
        }

        if (! $this->lookup($module, $to)) {
            throw new DomainException("Target status '{$to}' is unknown for module '{$module}'.");
        }
    }

    public function lookup(string $module, string $key): ?WorkflowStatus
    {
        return $this->all($module)->firstWhere('key', $key);
    }

    /** @return Collection<int, WorkflowStatus> */
    public function all(string $module): Collection
    {
        return $this->cache[$module] ??= WorkflowStatus::query()
            ->where('module', $module)
            ->orderBy('sort_order')
            ->get();
    }

    public function flushCache(): void
    {
        $this->cache = [];
    }
}
