<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Curriculum;
use App\Models\CurriculumEntry;
use App\Models\Department;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectCategory;
use App\Services\ScheduleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates block schedules excluding NSTP subjects', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create();
    $department = Department::factory()->forCollege($college)->create();
    $program = Program::factory()->create(['code' => 'BSIT']);
    $curriculum = Curriculum::factory()->create(['program_id' => $program->id]);
    $category = SubjectCategory::factory()->create();

    $majorSubject = Subject::factory()->create(['code' => 'COMP101', 'title' => 'Intro to Computing']);
    $nstpSubject = Subject::factory()->create(['code' => 'NSTP1', 'title' => 'National Service Training Program 1']);

    CurriculumEntry::factory()->create([
        'curriculum_id' => $curriculum->id,
        'subject_id' => $majorSubject->id,
        'subject_category_id' => $category->id,
        'semester' => '1ST',
        'year_level' => 1,
    ]);

    CurriculumEntry::factory()->create([
        'curriculum_id' => $curriculum->id,
        'subject_id' => $nstpSubject->id,
        'subject_category_id' => $category->id,
        'semester' => '1ST',
        'year_level' => 1,
    ]);

    $service = app(ScheduleGenerationService::class);

    $generated = $service->generateBlockSchedules([
        'campus_id' => $campus->id,
        'college_id' => $college->id,
        'department_id' => $department->id,
        'program_id' => $program->id,
        'program_code' => $program->code,
        'year_level' => 1,
        'semester' => '1ST',
        'school_year' => '2026-2027',
        'section_count' => 2,
        'slots' => 40,
    ]);

    expect($generated)->toHaveCount(2)
        ->and($generated->pluck('subject_id')->unique()->all())->toBe([$majorSubject->id]);

    $this->assertDatabaseCount('schedules', 2);
    $this->assertDatabaseCount('schedule_section', 2);
    $this->assertDatabaseHas('schedule_section', ['computed_section_name' => 'BSIT1-1']);
    $this->assertDatabaseHas('schedule_section', ['computed_section_name' => 'BSIT1-2']);
});

it('enforces NSTP CWTS slot limit for custom sections', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create();
    $subject = Subject::factory()->create(['code' => 'NSTP2-CWTS', 'title' => 'NSTP 2 CWTS']);

    $service = app(ScheduleGenerationService::class);

    expect(fn () => $service->createCustomSectionSchedule([
        'campus_id' => $campus->id,
        'college_id' => $college->id,
        'department_id' => null,
        'subject_id' => $subject->id,
        'program_code' => 'BSIT',
        'year_level' => 1,
        'section_identifier' => 'CWTS-1',
        'section_type' => 'NSTP',
        'semester' => '1ST',
        'school_year' => '2026-2027',
        'slots' => 81,
        'nstp_track' => 'CWTS',
    ]))->toThrow(ValidationException::class);
});
