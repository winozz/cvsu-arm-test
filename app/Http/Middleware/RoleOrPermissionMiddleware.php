<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleOrPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, $role, ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Allow user if has specified role
        if ($user->hasRole($role)) {
            return $next($request);
        }

        // Allow user if has specified permissions
        if (count($permissions) > 0 && $user->hasAnyPermission($permissions)) {
            return $next($request);
        }

        // If user is not authorized to access page/method/function
        abort(403, 'Unauthorized to access this resource');
    }
}
