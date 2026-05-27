<?php

namespace Database\Seeders;

use App\Models\User;
use App\Tenants\Modules\HRM\Models\LeaveType;
use App\Tenants\Modules\IAM\Models\Permission;
use App\Tenants\Modules\IAM\Models\Role;
use App\Tenants\Modules\IAM\Models\WorkflowStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a TENANT database. Invoked by `php artisan tenants:seed`
     * (configured via tenancy.seeder_parameters in config/tenancy.php).
     */
    public function run(): void
    {
        // Passport queries oauth_clients on the active connection, which is the
        // tenant DB when tenancy is initialized. Each tenant therefore needs its
        // own personal-access client row.
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

        $this->seedPermissions();
        $this->seedRolesAndUsers();
        $this->seedWorkflowStatuses();
        $this->seedLeaveDefaults();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            // IAM
            ['name' => 'iam.users.view',         'description' => 'View users'],
            ['name' => 'iam.users.create',       'description' => 'Create users'],
            ['name' => 'iam.users.edit',         'description' => 'Edit users'],
            ['name' => 'iam.users.delete',       'description' => 'Delete users'],
            ['name' => 'iam.roles.view',         'description' => 'View roles'],
            ['name' => 'iam.roles.create',       'description' => 'Create roles'],
            ['name' => 'iam.roles.edit',         'description' => 'Edit roles'],
            ['name' => 'iam.roles.delete',       'description' => 'Delete roles'],
            ['name' => 'iam.permissions.view',   'description' => 'View permissions'],
            ['name' => 'iam.permissions.assign', 'description' => 'Assign permissions to roles'],

            // HRM — module.feature.action (per skills/hrm/rules.md)
            ['name' => 'hrm.employee.read',     'description' => 'View employees'],
            ['name' => 'hrm.employee.write',    'description' => 'Create/update employees'],
            ['name' => 'hrm.employee.delete',   'description' => 'Terminate employees'],
            ['name' => 'hrm.employee.export',   'description' => 'Export employee data'],
            ['name' => 'hrm.attendance.read',   'description' => 'View attendance records'],
            ['name' => 'hrm.attendance.write',  'description' => 'Create/update attendance records'],
            ['name' => 'hrm.attendance.delete', 'description' => 'Delete attendance records'],
            ['name' => 'hrm.attendance.export', 'description' => 'Export attendance records'],
            ['name' => 'hrm.payroll.read',      'description' => 'View payroll'],
            ['name' => 'hrm.payroll.write',     'description' => 'Run payroll / manage components'],
            ['name' => 'hrm.payroll.export',    'description' => 'Export payroll data'],
            ['name' => 'hrm.leave.read',        'description' => 'View leave requests'],
            ['name' => 'hrm.leave.write',       'description' => 'Submit/approve leave'],
            ['name' => 'hrm.leave.delete',      'description' => 'Cancel leave requests'],
            ['name' => 'hrm.leave.export',      'description' => 'Export leave data'],
            ['name' => 'hrm.performance.read',  'description' => 'View appraisals'],
            ['name' => 'hrm.performance.write', 'description' => 'Manage appraisals'],
            ['name' => 'hrm.performance.export','description' => 'Export performance data'],
            ['name' => 'hrm.recruitment.read',  'description' => 'View vacancies and applications'],
            ['name' => 'hrm.recruitment.write', 'description' => 'Manage vacancies, applications, interviews'],
            ['name' => 'hrm.recruitment.delete','description' => 'Delete recruitment records'],
            ['name' => 'hrm.recruitment.export','description' => 'Export recruitment data'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], ['description' => $perm['description']]);
        }
    }

    private function seedRolesAndUsers(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin'], ['description' => 'Full access to all modules']);
        $staffRole  = Role::firstOrCreate(['name' => 'staff'],       ['description' => 'Standard staff access']);

        $superAdmin->permissions()->sync(Permission::pluck('id')->toArray());

        // Staff baseline: an ordinary employee should be able to clock in,
        // see their own attendance, and submit/track leave requests. They
        // do NOT get hrm.employee.read because that would let them browse
        // the entire org chart — keep that admin-only until we add a
        // dedicated `hrm.employee.read_own` perm.
        $staffPermNames = [
            'hrm.attendance.read',
            'hrm.attendance.write',
            'hrm.leave.read',
            'hrm.leave.write',
            'hrm.payroll.read',
        ];
        $staffPermIds = Permission::whereIn('name', $staffPermNames)->pluck('id')->all();
        $staffRole->permissions()->sync($staffPermIds);

        $admin = User::firstOrCreate(
            ['email' => 'admin@erp.local'],
            ['name' => 'ERP Administrator', 'handle' => 'admin', 'password' => Hash::make('Admin@1234!'), 'is_active' => true]
        );
        $admin->update(['role_id' => $superAdmin->id]);

        User::firstOrCreate(
            ['email' => 'staff@erp.local'],
            ['name' => 'Demo Staff', 'handle' => 'staff', 'password' => Hash::make('Staff@1234!'), 'role_id' => $staffRole->id, 'is_active' => true]
        );
    }

    /**
     * Seed default lifecycle statuses for every HRM state machine.
     * Idempotent — re-running won't dupe rows because of the (module,key) unique index.
     */
    public function seedWorkflowStatuses(): void
    {
        if (! Schema::hasTable('workflow_statuses')) {
            return;
        }

        $matrix = [
            'hrm.application' => [
                ['key' => 'applied',     'label' => 'Applied',     'color' => 'slate',  'is_initial' => true,  'allowed' => ['screening', 'rejected', 'withdrawn']],
                ['key' => 'screening',   'label' => 'Screening',   'color' => 'sky',    'allowed' => ['interview', 'rejected', 'withdrawn']],
                ['key' => 'interview',   'label' => 'Interview',   'color' => 'indigo', 'allowed' => ['offer', 'rejected', 'withdrawn']],
                ['key' => 'offer',       'label' => 'Offer',       'color' => 'amber',  'allowed' => ['hired', 'rejected', 'withdrawn']],
                ['key' => 'hired',       'label' => 'Hired',       'color' => 'emerald','is_terminal' => true],
                ['key' => 'rejected',    'label' => 'Rejected',    'color' => 'rose',   'is_terminal' => true],
                ['key' => 'withdrawn',   'label' => 'Withdrawn',   'color' => 'stone',  'is_terminal' => true],
            ],
            'hrm.leave' => [
                ['key' => 'pending',  'label' => 'Pending',  'color' => 'amber',   'is_initial' => true, 'allowed' => ['approved', 'rejected']],
                ['key' => 'approved', 'label' => 'Approved', 'color' => 'emerald', 'is_terminal' => true],
                ['key' => 'rejected', 'label' => 'Rejected', 'color' => 'rose',    'is_terminal' => true],
            ],
            'hrm.appraisal' => [
                ['key' => 'draft',     'label' => 'Draft',     'color' => 'slate',   'is_initial' => true, 'allowed' => ['submitted']],
                ['key' => 'submitted', 'label' => 'Submitted', 'color' => 'sky',     'allowed' => ['reviewed']],
                ['key' => 'reviewed',  'label' => 'Reviewed',  'color' => 'indigo',  'allowed' => ['closed']],
                ['key' => 'closed',    'label' => 'Closed',    'color' => 'emerald', 'is_terminal' => true],
            ],
            'hrm.vacancy' => [
                ['key' => 'draft',     'label' => 'Draft',     'color' => 'slate',   'is_initial' => true, 'allowed' => ['open', 'closed']],
                ['key' => 'open',      'label' => 'Open',      'color' => 'emerald', 'allowed' => ['closed', 'filled']],
                ['key' => 'closed',    'label' => 'Closed',    'color' => 'stone',   'is_terminal' => true],
                ['key' => 'filled',    'label' => 'Filled',    'color' => 'indigo',  'is_terminal' => true],
            ],
            'hrm.employee' => [
                ['key' => 'active',     'label' => 'Active',     'color' => 'emerald', 'is_initial' => true, 'allowed' => ['terminated']],
                ['key' => 'terminated', 'label' => 'Terminated', 'color' => 'rose',    'is_terminal' => true],
            ],
            'hrm.payroll_period' => [
                ['key' => 'draft',      'label' => 'Draft',      'color' => 'slate',   'is_initial' => true, 'allowed' => ['closed']],
                ['key' => 'closed',     'label' => 'Closed',     'color' => 'emerald', 'is_terminal' => true],
            ],
        ];

        foreach ($matrix as $module => $rows) {
            foreach ($rows as $i => $row) {
                WorkflowStatus::firstOrCreate(
                    ['module' => $module, 'key' => $row['key']],
                    [
                        'label'               => $row['label'],
                        'color'               => $row['color'] ?? null,
                        'is_initial'          => $row['is_initial']  ?? false,
                        'is_terminal'         => $row['is_terminal'] ?? false,
                        'allowed_transitions' => $row['allowed']     ?? [],
                        'sort_order'          => $i,
                    ]
                );
            }
        }
    }

    private function seedLeaveDefaults(): void
    {
        if (! Schema::hasTable('leave_types')) {
            return;
        }

        $defaults = [
            ['code' => 'ANNUAL', 'name' => 'Annual Leave', 'default_balance' => 18, 'is_paid' => true,  'accrues' => true,  'requires_approval' => true,  'color' => 'emerald'],
            ['code' => 'SICK',   'name' => 'Sick Leave',   'default_balance' => 12, 'is_paid' => true,  'accrues' => true,  'requires_approval' => false, 'color' => 'rose'],
            ['code' => 'UNPAID', 'name' => 'Unpaid Leave', 'default_balance' => 0,  'is_paid' => false, 'accrues' => false, 'requires_approval' => true,  'color' => 'stone'],
        ];

        foreach ($defaults as $row) {
            LeaveType::firstOrCreate(['code' => $row['code']], $row);
        }
    }
}
