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
            ->middleware(['permission:faculty_profiles.view', 'faculty_profile_scope:department'])
            ->name('faculty-profiles.index');
        Route::livewire('/faculty-profiles/{facultyProfile}', 'pages::dept-admin.faculty-profiles.show')
            ->middleware(['permission:faculty_profiles.view', 'faculty_profile_scope:department'])
            ->name('faculty-profiles.show');

        // Rooms Routes
        Route::livewire('/rooms', 'pages::dept-admin.rooms.index')
            ->middleware(['permission:rooms.view', 'room_scope:department'])
            ->name('rooms.index');

        // Schedule pages (separated)
        Route::livewire('/schedules', 'pages::dept-admin.schedules.index')
            ->middleware('permission:schedules.view')
            ->name('schedules.index');

        Route::livewire('/schedules/bulk-generate', 'pages::dept-admin.schedules.bulk-generate')
            ->middleware('permission:schedules.create')
            ->name('schedules.bulk-generate');

        Route::livewire('/schedules/custom-section', 'pages::dept-admin.schedules.custom-section')
            ->middleware('permission:schedules.create')
            ->name('schedules.custom-section');

        Route::livewire('/schedules/plot', 'pages::dept-admin.schedules.plot')
            ->middleware('permission:schedules.assign')
            ->name('schedules.plot');

        Route::livewire('/schedules/service-requests', 'pages::dept-admin.schedules.service-requests')
            ->middleware('permission:schedules.view')
            ->name('schedules.service-requests');
    });
