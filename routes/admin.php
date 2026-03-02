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
        Route::livewire('/branches/{branch}', 'pages::admin.branch.show')->name('branches.show');

        // Users routes
        Route::livewire('/users', 'pages::admin.users.index')->name('users');
        Route::livewire('/users/{user}', 'pages::admin.users.show')->name('users.show');

    });
