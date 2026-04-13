<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('department-admin')
    ->name('department-admin.')
    ->group(function () {
        Route::livewire('/dashboard', 'pages::dept-admin.dashboard.index')
            ->middleware('permission:schedules.assign')
            ->name('dashboard');
    });
