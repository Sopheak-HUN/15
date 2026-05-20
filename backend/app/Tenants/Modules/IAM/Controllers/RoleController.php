<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Tenants\Modules\IAM\Services\RoleService;
use App\Tenants\Modules\IAM\Models\Role;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function index()
    {
        return response()->json(['data' => $this->roleService->getAllRoles()]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|unique:roles,name']);
        
        $role = $this->roleService->createRole($request->only('name', 'description'));
        
        return response()->json(['success' => true, 'data' => $role]);
    }

    public function update(Request $request, Role $role)
    {
        $request->validate(['name' => 'required|string|unique:roles,name,' . $role->id]);

        $updatedRole = $this->roleService->updateRole($role, $request->only('name', 'description'));

        return response()->json(['success' => true, 'data' => $updatedRole]);
    }

    public function destroy(Role $role)
    {
        $this->roleService->deleteRole($role);
        return response()->json(['success' => true]);
    }
}
