<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the CENTRAL database.
     *
     * Tenant-scoped data (users, roles, permissions) is seeded per-tenant
     * via `php artisan tenants:seed` → TenantDatabaseSeeder.
     */
    public function run(): void
    {
        $existingClient = DB::table('oauth_clients')
            ->where('provider', 'users')
            ->where('grant_types', 'like', '%personal_access%')
            ->first();

        if (! $existingClient) {
            DB::table('oauth_clients')->insert([
                'id'            => (string) Str::uuid(),
                'name'          => 'ERP Personal Access Client',
                'secret'        => Str::random(40),
                'provider'      => 'users',
                'redirect_uris' => json_encode([]),
                'grant_types'   => json_encode(['personal_access']),
                'revoked'       => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }
}
