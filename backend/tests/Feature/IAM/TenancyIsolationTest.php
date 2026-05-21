<?php

use App\Models\Tenant;
use App\Tenants\Modules\IAM\Models\Role;

/*
|--------------------------------------------------------------------------
| P0 Tenancy Isolation — IAM endpoints
|--------------------------------------------------------------------------
| These tests verify the critical security invariant: a request scoped to
| Tenant A must never see, read, or mutate data belonging to Tenant B.
|
| They require an isolated test database (PostgreSQL with `tenants` schema
| and tenant DB provisioning enabled). Run with:
|
|     DB_DATABASE=erp_system_test php artisan test --filter=TenancyIsolation
|
| The suite skips itself when the central connection isn't reachable, so it
| won't blow up in CI environments that haven't been wired for tenancy yet.
*/

beforeEach(function () {
    // Tenancy requires PostgreSQL multi-database; SQLite (the default
    // phpunit.xml driver) can't provision per-tenant DBs. The suite is
    // opt-in: point DB_CONNECTION=pgsql + DB_DATABASE=erp_system_test
    // and re-run, OR skip silently in CI.
    $central = config('tenancy.database.central_connection');
    $driver  = config("database.connections.{$central}.driver");
    if ($driver !== 'pgsql') {
        $this->markTestSkipped("Tenancy isolation requires pgsql central connection (got {$driver}).");
    }

    try {
        \Illuminate\Support\Facades\DB::connection($central)->getPdo();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Central connection unreachable — wire DB_DATABASE=erp_system_test to enable.');
    }

    if (! \Illuminate\Support\Facades\Schema::connection($central)->hasTable('tenants')) {
        $this->markTestSkipped('Central `tenants` table not migrated — run `php artisan migrate` first.');
    }

    // Provision two short-lived tenants for the test.
    $this->tenantA = Tenant::create(['id' => 'iso-a', 'name' => 'Iso A', 'handle' => 'iso-a']);
    $this->tenantB = Tenant::create(['id' => 'iso-b', 'name' => 'Iso B', 'handle' => 'iso-b']);
});

afterEach(function () {
    optional($this->tenantA ?? null)->delete();
    optional($this->tenantB ?? null)->delete();
});

it('does not leak roles across tenants', function () {
    tenancy()->initialize($this->tenantA);
    Role::create(['name' => 'OnlyInA']);

    tenancy()->initialize($this->tenantB);
    expect(Role::where('name', 'OnlyInA')->exists())
        ->toBeFalse('Role from Tenant A is visible inside Tenant B — multi-DB isolation broken.');

    tenancy()->end();
});

it('rejects API requests missing the tenant header', function () {
    $response = $this->postJson('/api/iam/roles', ['name' => 'NoTenant']);
    expect($response->status())->toBeIn([401, 404, 500]); // Stancl raises TenantCouldNotBeIdentifiedException
});

it('scopes audit log queries to the active tenant', function () {
    tenancy()->initialize($this->tenantA);
    Role::create(['name' => 'AuditA']);                       // emits an audit_logs row in Tenant A
    $countA = \App\Tenants\Modules\IAM\Models\AuditLog::count();

    tenancy()->initialize($this->tenantB);
    $countB = \App\Tenants\Modules\IAM\Models\AuditLog::count();

    expect($countA)->toBeGreaterThanOrEqual(1);
    expect($countB)->toBe(0);

    tenancy()->end();
});
