<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\IAM\Services\SettingService;
use Illuminate\Http\Request;

/**
 * Per-group settings CRUD. Two endpoints intentionally — the UI never
 * needs to write a single key in isolation, and the group payload
 * matches how a form panel renders (one section = one group).
 *
 * Validation is per-group inside this controller so each module owns
 * the shape of its own settings without a generic schema engine. As
 * more groups are added, fan out to dedicated request classes if the
 * branches here get unwieldy.
 */
class SettingController extends Controller
{
    public function __construct(protected SettingService $settings) {}

    public function show(string $group)
    {
        return response()->json([
            'data' => $this->mergedFor($group),
        ]);
    }

    public function update(Request $request, string $group)
    {
        $data = $request->validate($this->rulesFor($group));
        $this->settings->setMany($group, $data);

        return response()->json([
            'success' => true,
            'data'    => $this->mergedFor($group),
        ]);
    }

    /**
     * Merge stored overrides on top of defaults. Most groups are flat
     * scalars (attendance), but some (numbering) are objects per key —
     * those need a per-key merge so a partial override doesn't blow
     * away unspecified sub-fields.
     */
    private function mergedFor(string $group): array
    {
        $defaults  = $this->defaultsFor($group);
        $overrides = $this->settings->getGroup($group);

        if ($group === 'numbering') {
            $merged = [];
            foreach ($defaults as $type => $def) {
                $merged[$type] = array_merge($def, (array) ($overrides[$type] ?? []));
            }
            return $merged;
        }

        return array_merge($defaults, $overrides);
    }

    /**
     * Per-group defaults. Merged into the GET response so the frontend
     * always sees a populated payload, even before any override exists.
     */
    private function defaultsFor(string $group): array
    {
        return match ($group) {
            'attendance' => [
                'morning_late_after'   => '09:00:00',
                'afternoon_late_after' => '13:30:00',
                // ISO weekday short names. Cambodia's standard week is
                // Mon–Sat; tenants override per their policy.
                'working_days'         => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                'work_start_time'      => '08:00:00',
                'work_end_time'        => '17:00:00',
            ],
            // One object per code type. `next_number` is the counter
            // CodeGeneratorService increments on each mint; exposing it
            // here lets admins reset after a data import.
            'numbering' => [
                'employee'  => ['prefix' => 'TT-EMP-', 'start_from' => 1, 'digits' => 4, 'next_number' => 1],
                'quotation' => ['prefix' => 'TT-QUO-', 'start_from' => 1, 'digits' => 6, 'next_number' => 1],
                'invoice'   => ['prefix' => 'TT-INV-', 'start_from' => 1, 'digits' => 6, 'next_number' => 1],
                'asset'     => ['prefix' => 'TT-AST-', 'start_from' => 1, 'digits' => 4, 'next_number' => 1],
            ],
            default => [],
        };
    }

    private function rulesFor(string $group): array
    {
        return match ($group) {
            'attendance' => [
                'morning_late_after'   => 'required|date_format:H:i:s',
                'afternoon_late_after' => 'required|date_format:H:i:s',
                'working_days'         => 'required|array|min:1',
                'working_days.*'       => 'in:mon,tue,wed,thu,fri,sat,sun',
                'work_start_time'      => 'required|date_format:H:i:s',
                'work_end_time'        => 'required|date_format:H:i:s|after:work_start_time',
            ],
            'numbering' => $this->numberingRules(),
            default     => [],
        };
    }

    /**
     * Flatten the per-type rule set so we don't repeat the same five
     * lines four times. `start_from` is 0 or 1 — anything else makes
     * the preview misleading; `digits` capped at 10 since beyond that
     * the counter overflows int storage in practical terms.
     */
    private function numberingRules(): array
    {
        $rules = [];
        foreach (['employee', 'quotation', 'invoice', 'asset'] as $type) {
            $rules["{$type}"]              = 'required|array';
            $rules["{$type}.prefix"]       = 'required|string|max:32';
            $rules["{$type}.start_from"]   = 'required|integer|in:0,1';
            $rules["{$type}.digits"]       = 'required|integer|min:1|max:10';
            $rules["{$type}.next_number"]  = 'required|integer|min:0';
        }
        return $rules;
    }
}
