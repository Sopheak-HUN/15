<?php

namespace App\Tenants\Modules\IAM\Services;

use Illuminate\Support\Facades\Auth;
use Exception;

class AuthService
{
    public function attemptLogin(array $credentials)
    {
        if (!Auth::attempt($credentials)) {
            throw new Exception("Invalid credentials");
        }

        $user = Auth::user();
        $token = $user->createToken('API Token')->accessToken;

        // Eager-load the role so the frontend can show the role name and
        // gate the sidebar without an extra round-trip.
        $user->load('role:id,name,description');

        // Effective permissions walk the role's parent chain (see
        // Role::effectivePermissions). We surface them as a flat list of
        // names — the frontend only needs string matches like
        // 'hrm.employee.read' to gate UI. Backend re-checks on every
        // request via the `permission:` middleware, so the array is
        // safe to expose.
        $permissions = $user->role
            ? $user->role->effectivePermissions()->pluck('name')->values()->all()
            : [];

        $userPayload = $user->toArray();
        $userPayload['effective_permissions'] = $permissions;

        return [
            'user'  => $userPayload,
            'token' => $token,
        ];
    }
}
