<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Tenants\Modules\IAM\Models\Permission;
use App\Tenants\Modules\IAM\Models\Role;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function getAllPermissions()
    {
        return Permission::all();
    }

    public function syncRolePermissions(Role $role, array $permissionIds): Role
    {
        return DB::transaction(function () use ($role, $permissionIds) {
            $role->permissions()->sync($permissionIds);
            return $role->load('permissions');
        });
    }
}
