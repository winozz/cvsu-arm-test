<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class GuestsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the user is already logged in, redirect them based on their role
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('superAdmin')) {
                // Redirect to admin dashboard
                return redirect()->route('admin.dashboard');
            } elseif ($user->hasRole('faculty')) {
                // Redirect to faculty dashboard
                return redirect()->route('faculty.dashboard');
            } else {
                // Redirect to home/login page
                return redirect('/');
            }
        }

        // If not logged in, allow them to view the guest page (e.g., login)
        return $next($request);
    }
}
