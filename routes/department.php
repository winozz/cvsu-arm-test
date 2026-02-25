<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:deptAdmin'])
    ->prefix('department-admin')
    ->name('department-admin.')
    ->group(function () {
        Route::livewire('/dashboard', 'pages::dept-admin.dashboard')->name('dashboard');
    });
