<?php

use App\Livewire\Tables\CollegeAdmin\AllSubjectsTable;
use App\Livewire\Tables\CollegeAdmin\AssignedSubjectsTable;
use App\Livewire\Tables\CollegeAdmin\SubjectRequestsTable;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\SubjectAssignmentRequest;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

describe('subject tables', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->campus = Campus::factory()->create([
            'name' => 'Main Campus',
            'code' => 'CvSU-Main',
        ]);
        $this->college = College::factory()->forCampus($this->campus)->create([
            'code' => 'CAS',
        ]);
        $this->department = Department::factory()->forCollege($this->college)->create();
        $this->user = User::factory()->collegeAdmin()->create();
        $this->user->employeeProfile->update([
            'campus_id' => $this->campus->id,
            'college_id' => $this->college->id,
            'department_id' => $this->department->id,
        ]);
    });

    it('assigned subjects table shows only current scope assignments and the user draft', function () {
        $submittedInScope = Subject::factory()->create();
        $submittedOutsideScope = Subject::factory()->create();
        $ownDraft = Subject::factory()->draft()->create(['created_by' => $this->user->id]);
        $otherDraft = Subject::factory()->draft()->create(['created_by' => User::factory()->create()->id]);

        SubjectAssignment::create([
            'subject_id' => $submittedInScope->id,
            'campus_id' => $this->campus->id,
            'college_id' => $this->college->id,
        ]);

        $otherCollege = College::factory()->forCampus($this->campus)->create();

        SubjectAssignment::create([
            'subject_id' => $submittedOutsideScope->id,
            'campus_id' => $this->campus->id,
            'college_id' => $otherCollege->id,
        ]);

        $ids = Livewire::actingAs($this->user)
            ->test(AssignedSubjectsTable::class, [
                'campusId' => $this->campus->id,
                'collegeId' => $this->college->id,
                'userId' => $this->user->id,
            ])
            ->instance()
            ->datasource()
            ->pluck('id')
            ->all();

        expect($ids)->toContain($submittedInScope->id, $ownDraft->id)
            ->not->toContain($submittedOutsideScope->id, $otherDraft->id);
    });

    it('all subjects table excludes drafts from the shared catalog list', function () {
        $submitted = Subject::factory()->create();
        $draft = Subject::factory()->draft()->create(['created_by' => $this->user->id]);

        $ids = Livewire::actingAs($this->user)
            ->test(AllSubjectsTable::class, [
                'campusId' => $this->campus->id,
                'collegeId' => $this->college->id,
            ])
            ->instance()
            ->datasource()
            ->pluck('id')
            ->all();

        expect($ids)->toContain($submitted->id)
            ->not->toContain($draft->id);
    });

    it('subject requests table separates incoming and outgoing pending requests', function () {
        $subject = Subject::factory()->create();
        $otherCampus = Campus::factory()->create([
            'name' => 'Bacoor City Campus',
            'code' => 'CvSU-Bacoor',
        ]);

        $otherRequester = User::factory()->create();
        $otherRequester->givePermissionTo('subjects.view');
        EmployeeProfile::create([
            'user_id' => $otherRequester->id,
            'employee_no' => 'EMP-OTHER',
            'first_name' => 'Other',
            'middle_name' => null,
            'last_name' => 'Requester',
            'position' => 'Campus Subject Manager',
            'campus_id' => $otherCampus->id,
            'college_id' => College::factory()->forCampus($otherCampus)->create()->id,
            'department_id' => null,
        ]);

        $incoming = SubjectAssignmentRequest::create([
            'subject_id' => $subject->id,
            'request_type' => SubjectAssignmentRequest::TYPE_ASSIGN,
            'status' => SubjectAssignmentRequest::STATUS_PENDING,
            'source_campus_id' => $otherCampus->id,
            'source_college_id' => null,
            'target_campus_id' => $this->campus->id,
            'target_college_id' => $this->college->id,
            'requested_by' => $otherRequester->id,
        ]);

        $outgoing = SubjectAssignmentRequest::create([
            'subject_id' => $subject->id,
            'request_type' => SubjectAssignmentRequest::TYPE_TRANSFER,
            'status' => SubjectAssignmentRequest::STATUS_PENDING,
            'source_campus_id' => $this->campus->id,
            'source_college_id' => $this->college->id,
            'target_campus_id' => $otherCampus->id,
            'target_college_id' => null,
            'requested_by' => $this->user->id,
        ]);

        $incomingIds = Livewire::actingAs($this->user)
            ->test(SubjectRequestsTable::class, [
                'direction' => 'incoming',
                'campusId' => $this->campus->id,
                'collegeId' => $this->college->id,
                'userId' => $this->user->id,
            ])
            ->instance()
            ->datasource()
            ->pluck('id')
            ->all();

        $outgoingIds = Livewire::actingAs($this->user)
            ->test(SubjectRequestsTable::class, [
                'direction' => 'outgoing',
                'campusId' => $this->campus->id,
                'collegeId' => $this->college->id,
                'userId' => $this->user->id,
            ])
            ->instance()
            ->datasource()
            ->pluck('id')
            ->all();

        expect($incomingIds)->toContain($incoming->id)
            ->not->toContain($outgoing->id)
            ->and($outgoingIds)->toContain($outgoing->id)
            ->not->toContain($incoming->id);
    });
});
