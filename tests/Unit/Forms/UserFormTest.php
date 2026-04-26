<?php

use App\Livewire\Forms\Admin\UserForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Component;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('UserForm', function () {
    beforeEach(function () {
        $this->component = new class extends Component
        {
            public function render()
            {
                return null;
            }
        };
    });

    it('resets stale profile state when loading a standard user after a faculty user', function () {
        ensureRoles(['faculty']);

        $facultyUser = User::factory()->faculty()->create([
            'name' => 'Maria Clara Santos',
            'email' => 'maria@example.test',
        ]);
        $facultyUser->facultyProfile->update([
            'academic_rank' => 'Associate Professor I',
            'contactno' => '09170001111',
            'address' => 'Indang, Cavite',
            'sex' => 'Female',
            'birthday' => '1990-03-15',
        ]);

        $standardUser = User::factory()->create([
            'name' => 'Ana Maria Dela Cruz',
            'email' => 'ana@example.test',
        ]);

        $form = new UserForm($this->component, 'form');
        $form->setUser($facultyUser->fresh(['roles', 'facultyProfile', 'employeeProfile']));
        $form->setUser($standardUser->fresh(['roles', 'facultyProfile', 'employeeProfile']));

        expect($form->type)->toBe('standard')
            ->and($form->first_name)->toBe('Ana')
            ->and($form->middle_name)->toBe('Maria Dela')
            ->and($form->last_name)->toBe('Cruz')
            ->and($form->academic_rank)->toBe('')
            ->and($form->contactno)->toBe('')
            ->and($form->address)->toBe('')
            ->and($form->sex)->toBe('')
            ->and($form->birthday)->toBe('')
            ->and($form->position)->toBe('')
            ->and($form->campus_id)->toBeNull()
            ->and($form->college_id)->toBeNull()
            ->and($form->department_id)->toBeNull();
    });

    it('deduplicates roles while ensuring faculty access for faculty-based account types', function () {
        $form = new UserForm($this->component, 'form');
        $form->type = 'dual';
        $form->roles = ['faculty', 'deptAdmin', 'faculty'];

        expect($form->normalizedRoles())->toBe(['faculty', 'deptAdmin']);
    });

    it('resolves assignment details from the selected department when present', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create();

        $otherCampus = Campus::factory()->create();
        $otherCollege = College::factory()->forCampus($otherCampus)->create();

        $form = new UserForm($this->component, 'form');
        $form->campus_id = $otherCampus->id;
        $form->college_id = $otherCollege->id;
        $form->department_id = $department->id;

        expect($form->resolveAcademicAssignment())->toBe([
            'campus_id' => $campus->id,
            'college_id' => $college->id,
            'department_id' => $department->id,
        ]);
    });
});
