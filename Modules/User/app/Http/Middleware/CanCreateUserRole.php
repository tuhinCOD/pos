<?php

namespace Modules\User\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Role\Models\Role;

class CanCreateUserRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $creator = Auth::user();

        $targetRole = Role::findOrFail($request->role);

        if (!$targetRole) {
            return response()->json([
                'message' => 'Invalid role selected'
            ], 422);
        }

        $targetRoleName = $targetRole->name;

        if ($creator->role?->name === 'super admin') {
            if (in_array($targetRoleName, ['super admin', 'admin'])) {
                return $next($request);
            }
        }

        if ($creator->role?->name === 'admin') {
            if (in_array($targetRoleName, ['manager', 'cashier', 'admin', 'warehouse staff', 'user'])) {
                return $next($request);
            }
        }

        if ($creator->role?->name === 'manager') {
            if (in_array($targetRoleName, ['user'])) {
                return $next($request);
            }
        }

        if ($creator->role?->name === 'cashier') {
            if (in_array($targetRoleName, ['user'])) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Unauthorized to create this role'
        ], 403);
    }
}
