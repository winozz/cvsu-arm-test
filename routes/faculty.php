<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->group(function () {
        Route::livewire('/faculty/schedules', 'pages::faculty.schedules.index')
            ->middleware('permission:faculty_schedules.view')
            ->name('faculty-schedules.index');
    });
