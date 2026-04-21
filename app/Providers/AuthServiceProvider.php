<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('superAdmin') ? true : null;
        });

        Gate::define('rooms.menu', function ($user) {
            return $user->can('rooms.view')
                && filled($user->employeeProfile?->college_id)
                && filled($user->employeeProfile?->department_id);
        });
    }
}
