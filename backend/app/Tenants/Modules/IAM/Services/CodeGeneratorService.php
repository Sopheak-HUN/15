<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Tenants\Modules\IAM\Models\TenantSetting;
use Illuminate\Support\Facades\DB;

/**
 * Mints sequential codes for entities that need a human-readable ID
 * (employees, quotations, invoices, assets, …). Config is sourced
 * from the `numbering` settings group so admins control prefix /
 * padding / start-from from the Settings page.
 *
 * Race safety: `next()` runs inside a transaction with
 * `lockForUpdate()` on the settings row, so concurrent calls block
 * instead of colliding on the same number.
 *
 * Call site:
 *     $code = app(CodeGeneratorService::class)->next('employee');
 *     // → "TT-EMP-0001", then "TT-EMP-0002", …
 */
class CodeGeneratorService
{
    /**
     * Fallback shape per type. Mirrors SettingController::defaultsFor
     * so a brand-new tenant works without saving the page first.
     */
    private const FALLBACKS = [
        'employee'  => ['prefix' => 'TT-EMP-', 'start_from' => 1, 'digits' => 4, 'next_number' => 1],
        'quotation' => ['prefix' => 'TT-QUO-', 'start_from' => 1, 'digits' => 6, 'next_number' => 1],
        'invoice'   => ['prefix' => 'TT-INV-', 'start_from' => 1, 'digits' => 6, 'next_number' => 1],
        'asset'     => ['prefix' => 'TT-AST-', 'start_from' => 1, 'digits' => 4, 'next_number' => 1],
    ];

    /**
     * Allocate and return the next code for `$type`. The counter is
     * incremented atomically; subsequent calls see the new value.
     */
    public function next(string $type): string
    {
        if (! isset(self::FALLBACKS[$type])) {
            throw new \InvalidArgumentException("Unknown code type: {$type}");
        }

        return DB::transaction(function () use ($type) {
            $row = TenantSetting::query()
                ->where('group', 'numbering')
                ->where('key', $type)
                ->lockForUpdate()
                ->first();

            $cfg = array_merge(self::FALLBACKS[$type], (array) ($row?->value ?? []));

            $current = (int) ($cfg['next_number'] ?? $cfg['start_from']);
            $code    = $this->format($cfg['prefix'], $current, (int) $cfg['digits']);

            // Persist the incremented counter so the very next call
            // doesn't reissue the same number.
            $cfg['next_number'] = $current + 1;

            TenantSetting::updateOrCreate(
                ['group' => 'numbering', 'key' => $type],
                ['value' => $cfg],
            );

            return $code;
        });
    }

    /**
     * Format a code without consuming a number. Useful for previews
     * in the Settings UI and for migration tooling.
     */
    public function format(string $prefix, int $number, int $digits): string
    {
        return $prefix . str_pad((string) $number, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Peek at the next code without incrementing. Frontend uses this
     * via the settings payload (next_number is exposed in the JSON),
     * but server-side helpers also need a "what would it be" probe.
     */
    public function preview(string $type): string
    {
        $row = TenantSetting::query()
            ->where('group', 'numbering')
            ->where('key', $type)
            ->first();

        $cfg = array_merge(self::FALLBACKS[$type] ?? [], (array) ($row?->value ?? []));
        $current = (int) ($cfg['next_number'] ?? $cfg['start_from'] ?? 1);
        return $this->format($cfg['prefix'] ?? '', $current, (int) ($cfg['digits'] ?? 4));
    }
}
