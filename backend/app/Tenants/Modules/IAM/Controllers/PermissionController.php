<?php

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Tenants\Modules\IAM\Services\PermissionService;
use App\Tenants\Modules\IAM\Models\Role;

class PermissionController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function index()
    {
        return response()->json(['data' => $this->permissionService->getAllPermissions()]);
    }

    public function sync(Request $request, Role $role)
    {
        $request->validate(['permission_ids' => 'required|array']);

        $updatedRole = $this->permissionService->syncRolePermissions($role, $request->permission_ids);

        return response()->json(['success' => true, 'data' => $updatedRole]);
    }
}
