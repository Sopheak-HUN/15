<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use App\Tenants\Modules\IAM\Models\Role;
use App\Tenants\Modules\IAM\Services\RoleService;
use DomainException;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(protected RoleService $roleService) {}

    public function index()
    {
        return response()->json(['data' => $this->roleService->getAllRoles()]);
    }

    public function show(Role $role)
    {
        $role->load(['permissions', 'parent:id,name']);
        return response()->json([
            'data' => [
                'role'                  => $role,
                'effective_permissions' => $this->roleService->effectivePermissions($role),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|unique:roles,name',
            'description'    => 'nullable|string|max:255',
            'parent_role_id' => 'nullable|uuid|exists:roles,id',
        ]);

        try {
            $role = $this->roleService->createRole($data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $role]);
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name'           => 'required|string|unique:roles,name,' . $role->id,
            'description'    => 'nullable|string|max:255',
            'parent_role_id' => 'nullable|uuid|exists:roles,id',
        ]);

        try {
            $updated = $this->roleService->updateRole($role, $data);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'data' => $updated]);
    }

    public function destroy(Role $role)
    {
        $this->roleService->deleteRole($role);
        return response()->json(['success' => true]);
    }
}
