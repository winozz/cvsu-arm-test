<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('department-admin')
    ->name('department-admin.')
    ->group(function () {
        // Dashboard Routes
        Route::livewire('/dashboard', 'pages::dept-admin.dashboard.index')
            ->middleware('permission:schedules.assign')
            ->name('dashboard');

        // Faculty Profile Routes
        Route::livewire('/faculty-profiles', 'pages::dept-admin.faculty-profiles.index')
            ->middleware('permission:faculty_profiles.view')
            ->name('faculty-profiles');
        Route::livewire('/faculty-profiles/{facultyProfile}', 'pages::dept-admin.faculty-profiles.show')
            ->middleware('permission:faculty_profiles.view')
            ->name('faculty-profiles.show');

    });
