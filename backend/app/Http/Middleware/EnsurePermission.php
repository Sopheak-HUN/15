<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route by one or more permission names.
 *
 *   Route::middleware('permission:hrm.employee.read')->group(...)
 *   Route::post('/api/iam/roles', ...)->middleware('permission:iam.roles.create')
 *
 * Multiple comma-separated names act as OR — having any one is enough,
 * matching how nav items usually map to "is the module accessible at all"
 * rather than "can the user perform this exact action."
 *
 * Super-admin bypasses the check entirely so newly-added permissions
 * don't lock administrators out before the catalog is reseeded.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Eager-load the role + its perms once per request. Without this
        // the loop below triggers N+1 queries for every checked permission.
        if (! $user->relationLoaded('role')) {
            $user->load('role.permissions:id,name');
        }

        // Convention: the `super-admin` role bypasses gating. This keeps
        // the local dev `admin@erp.local` account usable even when new
        // permissions are added to the catalog mid-development before
        // the role_permission pivot is re-synced.
        if ($user->role?->name === 'super-admin') {
            return $next($request);
        }

        $userPerms = $user->role
            ? $user->role->effectivePermissions()->pluck('name')->all()
            : [];

        foreach ($permissions as $needed) {
            if (in_array($needed, $userPerms, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden. Missing permission: ' . implode(' or ', $permissions),
        ], 403);
    }
}
