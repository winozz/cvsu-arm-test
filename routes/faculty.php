<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->group(function () {
        Route::livewire('/dashboard/faculty', 'pages::faculty.dashboard.index')
            ->middleware('permission:faculty_schedules.view')
            ->name('dashboard.faculty');
    });
