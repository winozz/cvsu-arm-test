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
            $profile = $user->departmentManagementProfile();

            return $user->can('rooms.view')
                && filled($profile?->college_id)
                && filled($profile?->department_id);
        });
    }
}
