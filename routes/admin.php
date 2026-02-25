<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:superAdmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard routes
        Route::livewire('/dashboard', 'pages::admin.dashboard.index')->name('dashboard');

        // Branches routes
        Route::livewire('/branches', 'pages::admin.branch.index')->name('branches');

        // Departments routes
        Route::livewire('/departments', 'pages::admin.department.index')->name('departments');

    });
