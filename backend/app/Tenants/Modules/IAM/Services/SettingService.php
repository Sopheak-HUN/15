<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Tenants\Modules\IAM\Models\TenantSetting;
use Illuminate\Support\Collection;

/**
 * Reads / writes per-tenant settings. The intended call sites are:
 *   - SettingService::get('attendance.morning_late_after', '09:00:00')
 *   - SettingService::getGroup('attendance')
 *   - SettingService::set('attendance.morning_late_after', '08:30:00')
 *   - SettingService::setMany('attendance', [...])
 *
 * Request-scoped in-memory cache keeps repeated reads cheap (the
 * attendance late-threshold lookups would otherwise fire on every
 * check-in/break-in). Cache is cleared automatically on each write.
 *
 * Defaults are inlined at the call site (`get($key, $default)`) rather
 * than seeded in the DB — that way an upgrade that adds a new setting
 * works without a data migration, and turning off a tenant override
 * simply means deleting the row.
 */
class SettingService
{
    /** @var array<string, mixed>|null  group.key => value */
    private ?array $cache = null;

    public function get(string $dotted, mixed $default = null): mixed
    {
        $this->primeCache();
        return $this->cache[$dotted] ?? $default;
    }

    /**
     * Returns every key in a group as a flat key => value map (without
     * the group prefix).
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array
    {
        $this->primeCache();
        $out = [];
        $prefix = $group . '.';
        foreach ($this->cache as $dotted => $value) {
            if (str_starts_with($dotted, $prefix)) {
                $out[substr($dotted, strlen($prefix))] = $value;
            }
        }
        return $out;
    }

    public function set(string $dotted, mixed $value): TenantSetting
    {
        [$group, $key] = $this->split($dotted);
        $row = TenantSetting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value],
        );
        $this->cache = null;  // invalidate
        return $row;
    }

    /**
     * Bulk-write every key in a single group. Keys not in `$pairs` are
     * left untouched (no destructive delete) — set to null/empty
     * explicitly to clear a value.
     *
     * @param array<string, mixed> $pairs
     * @return Collection<int, TenantSetting>
     */
    public function setMany(string $group, array $pairs): Collection
    {
        $written = collect();
        foreach ($pairs as $key => $value) {
            $written->push(TenantSetting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value],
            ));
        }
        $this->cache = null;
        return $written;
    }

    private function primeCache(): void
    {
        if ($this->cache !== null) return;
        $this->cache = [];
        foreach (TenantSetting::query()->get(['group', 'key', 'value']) as $row) {
            $this->cache[$row->group . '.' . $row->key] = $row->value;
        }
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function split(string $dotted): array
    {
        $pos = strpos($dotted, '.');
        if ($pos === false || $pos === 0) {
            throw new \InvalidArgumentException("Setting key must be group.key, got '{$dotted}'.");
        }
        return [substr($dotted, 0, $pos), substr($dotted, $pos + 1)];
    }
}
