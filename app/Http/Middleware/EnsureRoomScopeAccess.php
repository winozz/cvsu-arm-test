<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoomScopeAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $user = $request->user()?->loadMissing(['employeeProfile', 'facultyProfile']);

        abort_unless($user && $user->can('rooms.view'), 403);

        $allowed = match ($scope) {
            'college' => $user->canAccessCollegeRooms(),
            'department' => $user->canAccessDepartmentRooms(),
            default => false,
        };

        abort_unless($allowed, 403);

        return $next($request);
    }
}
