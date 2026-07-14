<?php

namespace Modules\Role\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
            $user = Auth::user();

            if (!$user || !in_array($user->role->name, $roles)) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 403);
            }

        return $next($request);
    }
}
