<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Tenants\Modules\IAM\Models\Role;

class RoleService
{
    public function getAllRoles()
    {
        return Role::with('permissions')->get();
    }

    public function createRole(array $data): Role
    {
        return Role::create($data);
    }

    public function updateRole(Role $role, array $data): Role
    {
        $role->update($data);
        return $role;
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }
}
