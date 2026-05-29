<?php

namespace App\Tenants\Modules\IAM\Models;

use App\Tenants\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-tenant key/value setting. Use SettingService instead of touching
 * this model directly — the service centralises caching and group
 * lookups so callers don't sprinkle Eloquent queries across the codebase.
 */
class TenantSetting extends Model
{
    use HasUuids, Auditable;

    protected $guarded = [];

    protected $casts = [
        // Storing arbitrary JSON lets the same column hold "09:00:00"
        // (string), ["mon","tue"] (array), or true (boolean) without
        // type juggling at the call site.
        'value' => 'array',
    ];
}
