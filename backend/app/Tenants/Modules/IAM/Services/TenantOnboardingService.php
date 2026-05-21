<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Models\Tenant;
use Throwable;

class TenantOnboardingService
{
    public function onboard(string $name, string $handle): Tenant
    {
        // PostgreSQL forbids CREATE DATABASE inside a transaction, and
        // stancl/tenancy fires CreateDatabase synchronously on Tenant::create.
        // We can't wrap this in DB::transaction(); instead we roll back manually
        // if the domain insert fails after the tenant row + tenant DB exist.
        $tenant = Tenant::create([
            'id'     => $handle,
            'name'   => $name,
            'handle' => $handle,
            'status' => 'active',
        ]);

        try {
            $domain = config('tenancy.central_domains')[0] ?? 'localhost';
            $tenant->domains()->create(['domain' => $handle . '.' . $domain]);
        } catch (Throwable $e) {
            $tenant->delete();
            throw $e;
        }

        return $tenant;
    }
}
