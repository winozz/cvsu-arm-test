<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'room_scope' => \App\Http\Middleware\EnsureRoomScopeAccess::class,
            'faculty_profile_scope' => \App\Http\Middleware\EnsureFacultyProfileScopeAccess::class,

            // Custom guests middleware
            'guests' => \App\Http\Middleware\GuestsMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (UnauthorizedException $e, Request $request) {
            // Redirects unauthorized access to the resolver route in web.php
            return redirect()->route('dashboard')
                ->with('error', 'You do not have permission to access that page.');
        });
    })->create();
