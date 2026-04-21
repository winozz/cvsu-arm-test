<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->group(function () {
        // Dashboard Routes
        Route::livewire('/dashboard/college', 'pages::college-admin.dashboard.index')
            ->middleware('permission:departments.view')
            ->name('dashboard.college');

        // Departments Management Routes
        Route::livewire('/departments', 'pages::college-admin.departments.index')
            ->middleware('permission:departments.view')
            ->name('departments.index');

        // Programs Management Routes
        Route::livewire('/programs', 'pages::college-admin.programs.index')
            ->middleware('permission:programs.view')
            ->name('programs.index');

        Route::livewire('/college/faculty-profiles', 'pages::dept-admin.faculty-profiles.index')
            ->middleware('permission:faculty_profiles.view')
            ->name('college-faculty-profiles.index');
        Route::livewire('/college/faculty-profiles/{facultyProfile}', 'pages::dept-admin.faculty-profiles.show')
            ->middleware('permission:faculty_profiles.view')
            ->name('college-faculty-profiles.show');
    });
