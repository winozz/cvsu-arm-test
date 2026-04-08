<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:superAdmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard Routes
        Route::livewire('/dashboard', 'pages::admin.dashboard.index')->name('dashboard');

        // Campus/College Routes
        Route::livewire('/campuses', 'pages::admin.campuses.index')->name('campuses');
        Route::livewire('/campuses/{campus}', 'pages::admin.colleges.index')->name('campuses.show');
        Route::livewire('/campuses/{campus}/{college}', 'pages::admin.departments.index')->name('campuses.college.show');

        // Users Routes
        Route::livewire('/users', 'pages::admin.users.index')->name('users');
        Route::livewire('/users/{user}', 'pages::admin.users.show')->name('users.show');

        // Faculty Profile Routes
        Route::livewire('/faculty-profiles', 'pages::admin.faculty-profiles.index')->name('faculty-profiles');
        Route::livewire('/faculty-profiles/{facultyProfile}', 'pages::admin.faculty-profiles.show')->name('faculty-profiles.show');

        // Roles Management Routes
        Route::livewire('/roles', 'pages::admin.roles.index')->name('roles');

        // Permission Management Routes
        Route::livewire('/permissions', 'pages::admin.permissions.index')->name('permissions');
    });
