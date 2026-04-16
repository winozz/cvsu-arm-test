<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

describe('department admin faculty profiles page', function () {
    beforeEach(function () {
        ensureRoles(['faculty', 'deptAdmin']);

        $this->user = actingUserWithPermissions([
            'faculty_profiles.view',
            'faculty_profiles.create',
        ], ['deptAdmin']);

        $this->campus = Campus::factory()->create();
        $this->college = College::factory()->forCampus($this->campus)->create();
        $this->department = Department::factory()->forCollege($this->college)->create();
    });

    it('loads dependent colleges and departments when the academic assignment changes', function () {
        Livewire::actingAs($this->user)
            ->test('pages::dept-admin.faculty-profiles.index')
            ->call('create')
            ->set('form.campus_id', $this->campus->id)
            ->assertSet('form.college_id', null)
            ->assertSet('form.department_id', null)
            ->set('form.college_id', $this->college->id)
            ->assertSet('form.department_id', null)
            ->assertCount('colleges', 1)
            ->assertCount('departments', 1);
    });

    it('creates a faculty profile and linked faculty user', function () {
        Livewire::actingAs($this->user)
            ->test('pages::dept-admin.faculty-profiles.index')
            ->call('create')
            ->set('form.first_name', 'Mara')
            ->set('form.middle_name', 'N')
            ->set('form.last_name', 'Reyes')
            ->set('form.email', 'mara.reyes@example.test')
            ->set('form.campus_id', $this->campus->id)
            ->set('form.college_id', $this->college->id)
            ->set('form.department_id', $this->department->id)
            ->set('form.academic_rank', 'Assistant Professor I')
            ->set('form.contactno', '09181234567')
            ->set('form.sex', 'Female')
            ->set('form.address', 'Naic, Cavite')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('createModal', false)
            ->assertDispatched('pg:eventRefresh-facultyProfilesTable');

        $createdUser = User::query()->where('email', 'mara.reyes@example.test')->first();

        expect($createdUser)->not->toBeNull()
            ->and($createdUser->hasRole('faculty'))->toBeTrue()
            ->and($createdUser->facultyProfile)->not->toBeNull()
            ->and($createdUser->facultyProfile->department_id)->toBe($this->department->id)
            ->and($createdUser->facultyProfile->academic_rank)->toBe('Assistant Professor I');
    });
});
