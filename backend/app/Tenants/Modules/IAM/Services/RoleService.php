<?php

namespace App\Tenants\Modules\IAM\Services;

use App\Tenants\Modules\IAM\Models\Role;
use DomainException;

class RoleService
{
    public function getAllRoles()
    {
        return Role::with(['permissions', 'parent:id,name'])->get();
    }

    public function createRole(array $data): Role
    {
        $this->guardParent(null, $data['parent_role_id'] ?? null);
        return Role::create($this->whitelist($data));
    }

    public function updateRole(Role $role, array $data): Role
    {
        if (array_key_exists('parent_role_id', $data)) {
            $this->guardParent($role, $data['parent_role_id']);
        }
        $role->update($this->whitelist($data));
        return $role->fresh(['permissions', 'parent:id,name']);
    }

    public function deleteRole(Role $role): void
    {
        $role->delete();
    }

    /**
     * Walk the parent chain and return the full effective permission set.
     * Used by the API to show admins what a role *actually* grants.
     */
    public function effectivePermissions(Role $role)
    {
        return $role->loadMissing('permissions', 'parent.permissions')
            ->effectivePermissions();
    }

    private function whitelist(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'name', 'description', 'parent_role_id',
        ]));
    }

    /**
     * Stop a role from inheriting from itself or from one of its descendants
     * (which would create a cycle in effectivePermissions()).
     */
    private function guardParent(?Role $role, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }
        if ($role && $parentId === $role->id) {
            throw new DomainException('A role cannot inherit from itself.');
        }
        $parent = Role::find($parentId);
        if (! $parent) {
            throw new DomainException('Parent role not found.');
        }
        if ($role && $role->isAncestorOf($parent)) {
            throw new DomainException('Inheritance would create a cycle.');
        }
    }
}
