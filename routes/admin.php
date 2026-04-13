<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard Routes
        Route::livewire('/dashboard', 'pages::admin.dashboard.index')
            ->middleware('permission:campuses.view')
            ->name('dashboard');

        // Campus/College Routes
        Route::livewire('/campuses', 'pages::admin.campuses.index')
            ->middleware('permission:campuses.view')
            ->name('campuses');
        Route::livewire('/campuses/{campus}', 'pages::admin.colleges.index')
            ->middleware('permission:colleges.view')
            ->name('campuses.show');
        Route::livewire('/campuses/{campus}/{college}', 'pages::admin.departments.index')
            ->middleware('permission:departments.view')
            ->name('campuses.college.show');

        // Users Routes
        Route::livewire('/users', 'pages::admin.users.index')
            ->middleware('permission:users.view')
            ->name('users');
        Route::livewire('/users/{user}', 'pages::admin.users.show')
            ->middleware('permission:users.view')
            ->name('users.show');

        // Roles Management Routes
        Route::livewire('/roles', 'pages::admin.roles.index')
            ->middleware('permission:roles.view')
            ->name('roles');

        // Permission Management Routes
        Route::livewire('/permissions', 'pages::admin.permissions.index')
            ->middleware('permission:permissions.view')
            ->name('permissions');

        // Direct role and permission assignments
        Route::livewire('/assignments', 'pages::admin.assignments.index')
            ->middleware('permission:assignments.manage')
            ->name('assignments');
    });
