<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GUEST ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('guests')->group(function () {
    Route::view('/', 'auth.login')->name('login');
    Route::redirect('/login', '/');

    Route::controller(GoogleAuthController::class)->group(function () {
        Route::get('/auth/google/redirect', 'redirect')->name('google.redirect');
        Route::get('/auth/google/callback', 'callback')->name('google.callback');
    });
});

/*
|--------------------------------------------------------------------------
| RESOLVER (SINGLE ENTRY POINT)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->get('/dashboard', function () {
    $dashboardRoute = Auth::user()?->dashboardRoute();

    abort_unless($dashboardRoute && Route::has($dashboardRoute), 403, 'You do not have a valid permission assigned.');

    return redirect()->route($dashboardRoute);
})->name('dashboard.resolve');

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/
Route::post('/logout', [GoogleAuthController::class, 'logout'])
    ->name('logout');

require __DIR__.'/admin.php';
require __DIR__.'/college.php';
require __DIR__.'/department.php';
require __DIR__.'/faculty.php';
