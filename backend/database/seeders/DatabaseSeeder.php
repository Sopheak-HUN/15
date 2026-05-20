<?php

namespace Database\Seeders;

use App\Models\User;
use App\Tenants\Modules\IAM\Models\Permission;
use App\Tenants\Modules\IAM\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Uses firstOrCreate / sync to stay idempotent (safe to re-run).
     */
    public function run(): void
    {
        // --- Passport: Personal Access Client (per-tenant) ---
        $existingClient = DB::table('oauth_clients')
            ->where('provider', 'users')
            ->where('grant_types', 'like', '%personal_access%')
            ->first();

        if (! $existingClient) {
            $clientId = (string) Str::uuid();

            DB::table('oauth_clients')->insert([
                'id'            => $clientId,
                'name'          => 'ERP Personal Access Client',
                'secret'        => Str::random(40),
                'provider'      => 'users',
                'redirect_uris' => json_encode([]),
                'grant_types'   => json_encode(['personal_access']),
                'revoked'       => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::table('oauth_personal_access_clients')->insert([
                'client_id'  => $clientId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // --- Permissions ---
        $permissions = [
            ['name' => 'iam.users.view',        'description' => 'View users'],
            ['name' => 'iam.users.create',       'description' => 'Create users'],
            ['name' => 'iam.users.edit',         'description' => 'Edit users'],
            ['name' => 'iam.users.delete',       'description' => 'Delete users'],
            ['name' => 'iam.roles.view',         'description' => 'View roles'],
            ['name' => 'iam.roles.create',       'description' => 'Create roles'],
            ['name' => 'iam.roles.edit',         'description' => 'Edit roles'],
            ['name' => 'iam.roles.delete',       'description' => 'Delete roles'],
            ['name' => 'iam.permissions.view',   'description' => 'View permissions'],
            ['name' => 'iam.permissions.assign', 'description' => 'Assign permissions to roles'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name']],
                ['description' => $perm['description']]
            );
        }

        // --- Roles ---
        $superAdmin = Role::firstOrCreate(
            ['name' => 'super-admin'],
            ['description' => 'Full access to all modules']
        );

        $staffRole = Role::firstOrCreate(
            ['name' => 'staff'],
            ['description' => 'Standard staff access']
        );

        // Assign all permissions to super-admin (sync = idempotent)
        $superAdmin->permissions()->sync(Permission::pluck('id')->toArray());

        // --- Default Admin User ---
        $admin = User::firstOrCreate(
            ['email' => 'admin@erp.local'],
            [
                'name'      => 'ERP Administrator',
                'handle'    => 'admin',
                'password'  => Hash::make('Admin@1234!'),
                'is_active' => true,
            ]
        );
        $admin->update(['role_id' => $superAdmin->id]);

        // --- Demo Staff User ---
        User::firstOrCreate(
            ['email' => 'staff@erp.local'],
            [
                'name'      => 'Demo Staff',
                'handle'    => 'staff',
                'password'  => Hash::make('Staff@1234!'),
                'role_id'   => $staffRole->id,
                'is_active' => true,
            ]
        );
    }
}
