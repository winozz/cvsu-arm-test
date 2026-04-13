<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:collegeAdmin'])
    ->prefix('college-admin')
    ->name('college-admin.')
    ->group(function () {
        // Dashboard Routes
        Route::livewire('/dashboard', 'pages::college-admin.dashboard')->name('dashboard');

        // Departments Management Routes
        Route::livewire('/departments', 'pages::college-admin.departments.index')->name('departments');
    });
