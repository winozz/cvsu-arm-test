<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:collegeAdmin'])
    ->prefix('college-admin')
    ->name('college-admin.')
    ->group(function () {
        Route::livewire('/dashboard', 'pages::college-admin.dashboard')->name('dashboard');
    });
