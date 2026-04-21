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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If the user is already logged in, redirect them based on their role
        if (Auth::check()) {
            return redirect()->route('dashboard.resolve');
        }

        // If not logged in, allow them to view the guest page (e.g., login)
        return $next($request);
    }
}
