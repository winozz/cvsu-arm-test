<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleFaculty;
use App\Models\ScheduleRoomTime;
use App\Models\Subject;
use App\Models\User;
use App\Services\ScheduleConflictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('detects overlapping faculty and room assignments', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create();
    $department = Department::factory()->forCollege($college)->create();
    $subject = Subject::factory()->create();
    $room = Room::factory()->create([
        'campus_id' => $campus->id,
        'college_id' => $college->id,
        'department_id' => $department->id,
    ]);
    $faculty = User::factory()->create();

    $schedule = Schedule::query()->create([
        'sched_code' => '260100001',
        'subject_id' => $subject->id,
        'campus_id' => $campus->id,
        'college_id' => $college->id,
        'department_id' => $department->id,
        'semester' => '1ST',
        'school_year' => '2026-2027',
        'slots' => 40,
        'status' => 'plotted',
    ]);

    ScheduleRoomTime::query()->create([
        'schedule_id' => $schedule->id,
        'room_id' => $room->id,
        'class_type' => 'LEC',
        'day' => 'MON',
        'time_in' => '08:00',
        'time_out' => '10:00',
    ]);

    ScheduleFaculty::query()->create([
        'schedule_id' => $schedule->id,
        'user_id' => $faculty->id,
        'class_type' => 'LEC',
    ]);

    $service = app(ScheduleConflictService::class);

    expect($service->hasFacultyConflict($faculty->id, 'MON', '09:00', '11:00'))->toBeTrue()
        ->and($service->hasRoomConflict($room->id, 'MON', '09:00', '11:00'))->toBeTrue()
        ->and($service->unavailableRoomIds($campus->id, 'MON', '09:00', '11:00'))->toContain($room->id);
});
