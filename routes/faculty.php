<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('faculty')
    ->name('faculty.')
    ->group(function () {
        Route::livewire('/dashboard', 'pages::faculty.dashboard.index')
            ->middleware('permission:faculty_schedules.view')
            ->name('dashboard');
    });
