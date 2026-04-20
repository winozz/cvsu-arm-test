<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('college-admin')
    ->name('college-admin.')
    ->group(function () {
        // Dashboard Routes
        Route::livewire('/dashboard', 'pages::college-admin.dashboard.index')
            ->middleware('permission:departments.view')
            ->name('dashboard');

        // Departments Management Routes
        Route::livewire('/departments', 'pages::college-admin.departments.index')
            ->middleware('permission:departments.view')
            ->name('departments');

        // Programs Management Routes
        Route::livewire('/programs', 'pages::college-admin.programs.index')
            ->middleware('permission:programs.view')
            ->name('programs');
    });
