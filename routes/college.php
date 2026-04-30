<?php

use App\Http\Controllers\ScheduleServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])
    ->group(function () {
        // Dashboard Routes
        Route::livewire('/dashboard/college', 'pages::college-admin.dashboard.index')
            ->middleware('permission:departments.view')
            ->name('dashboard.college');

        // Departments Management Routes
        Route::livewire('/departments', 'pages::college-admin.departments.index')
            ->middleware('permission:departments.view')
            ->name('departments.index');

        // Programs Management Routes
        Route::livewire('/programs', 'pages::college-admin.programs.index')
            ->middleware('permission:programs.view')
            ->name('programs.index');

        // Faculty Profiles Management Routes
        Route::livewire('/college/faculty-profiles', 'pages::dept-admin.faculty-profiles.index')
            ->middleware(['permission:faculty_profiles.view', 'faculty_profile_scope:college'])
            ->name('college-faculty-profiles.index');
        Route::livewire('/college/faculty-profiles/{facultyProfile}', 'pages::dept-admin.faculty-profiles.show')
            ->middleware(['permission:faculty_profiles.view', 'faculty_profile_scope:college'])
            ->name('college-faculty-profiles.show');

        // Subjects Management Routes
        Route::livewire('/subjects', 'pages::college-admin.subjects.index')
            ->middleware('permission:subjects.view')
            ->name('subjects.index');

        // Rooms Management Routes
        Route::livewire('/college/rooms', 'pages::dept-admin.rooms.index')
            ->middleware(['permission:rooms.view', 'room_scope:college'])
            ->name('college-rooms.index');

        // Service requests dashboard route
        Route::livewire('/schedule-service-requests', 'pages::college-admin.schedule-service-requests.index')
            ->middleware('permission:schedules.view')
            ->name('schedule-service-requests.index');

        // Inter-college service request workflow routes
        Route::prefix('/api/schedule-service-requests')->group(function () {
            Route::post('/', [ScheduleServiceRequestController::class, 'store'])
                ->middleware('permission:schedules.view')
                ->name('schedule-service-requests.store');

            Route::get('/incoming', [ScheduleServiceRequestController::class, 'incoming'])
                ->middleware('permission:schedules.view')
                ->name('schedule-service-requests.incoming');

            Route::post('/{serviceRequestId}/respond', [ScheduleServiceRequestController::class, 'respond'])
                ->middleware('permission:schedules.view')
                ->name('schedule-service-requests.respond');

            Route::post('/{serviceRequestId}/assign-department', [ScheduleServiceRequestController::class, 'assignDepartment'])
                ->middleware('permission:schedules.view')
                ->name('schedule-service-requests.assign-department');
        });
    });
