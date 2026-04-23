<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\SubjectAssignmentRequest;
use App\Models\SubjectUserAction;
use App\Models\User;
use App\Support\SubjectDuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('tracks subject assignments, requests, and user actions through relations', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create();
    $user = User::factory()->create();
    $subject = Subject::factory()->create();

    $assignment = SubjectAssignment::create([
        'subject_id' => $subject->id,
        'campus_id' => $campus->id,
        'college_id' => $college->id,
    ]);

    $request = SubjectAssignmentRequest::create([
        'subject_id' => $subject->id,
        'request_type' => SubjectAssignmentRequest::TYPE_ASSIGN,
        'status' => SubjectAssignmentRequest::STATUS_PENDING,
        'source_campus_id' => $campus->id,
        'source_college_id' => $college->id,
        'target_campus_id' => $campus->id,
        'target_college_id' => $college->id,
        'requested_by' => $user->id,
    ]);

    $action = SubjectUserAction::create([
        'subject_id' => $subject->id,
        'user_id' => $user->id,
        'action' => 'draft_created',
        'description' => 'Draft subject created.',
    ]);

    expect($subject->fresh()->subjectAssignments->modelKeys())->toContain($assignment->id)
        ->and($subject->fresh()->subjectAssignmentRequests->modelKeys())->toContain($request->id)
        ->and($subject->fresh()->subjectUserActions->modelKeys())->toContain($action->id)
        ->and($request->fresh()->source_scope_label)->toBe(trim($campus->code.' / '.$college->code, ' /'))
        ->and($request->fresh()->target_scope_label)->toBe(trim($campus->code.' / '.$college->code, ' /'));
});

it('detects exact and similar duplicate subject conflicts', function () {
    Subject::factory()->create([
        'code' => 'MATH101',
        'title' => 'College Algebra',
    ]);
    Subject::factory()->create([
        'code' => 'MATH201',
        'title' => 'College Algebras',
    ]);

    $exactConflicts = SubjectDuplicateDetector::findConflicts(null, 'MATH101', 'College Algebra');
    $similarConflicts = SubjectDuplicateDetector::findConflicts(null, 'MATH102', 'College Algebraa');

    expect($exactConflicts['exact'])->not->toBeEmpty()
        ->and($similarConflicts['similar'])->not->toBeEmpty();
});
