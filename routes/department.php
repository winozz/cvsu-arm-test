<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->group(function () {
        // Dashboard Routes
        Route::livewire('/dashboard/department', 'pages::dept-admin.dashboard.index')
            ->middleware('permission:schedules.assign')
            ->name('dashboard.department');

        // Faculty Profile Routes
        Route::livewire('/faculty-profiles', 'pages::dept-admin.faculty-profiles.index')
            ->middleware('permission:faculty_profiles.view')
            ->name('faculty-profiles.index');
        Route::livewire('/faculty-profiles/{facultyProfile}', 'pages::dept-admin.faculty-profiles.show')
            ->middleware('permission:faculty_profiles.view')
            ->name('faculty-profiles.show');

        // Rooms Routes
        Route::livewire('/rooms', 'pages::dept-admin.rooms.index')
            ->middleware('permission:rooms.view')
            ->name('rooms.index');
    });
