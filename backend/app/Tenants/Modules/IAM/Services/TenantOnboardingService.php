<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantOnboardingService
{
    public function onboard(string $name, string $handle): Tenant
    {
        return DB::transaction(function () use ($name, $handle) {
            $tenant = Tenant::create([
                'id' => $handle,
                'name' => $name,
                'handle' => $handle,
                'status' => 'active',
            ]);

            // Assuming localhost as fallback if central_domains is empty
            $domain = config('tenancy.central_domains')[0] ?? 'localhost';
            $tenant->domains()->create(['domain' => $handle . '.' . $domain]);

            return $tenant;
        });
    }
}
