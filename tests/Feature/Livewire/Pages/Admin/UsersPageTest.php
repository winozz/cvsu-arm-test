<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

describe('admin users pages', function () {
    beforeEach(function () {
        ensureRoles(['faculty', 'deptAdmin', 'collegeAdmin']);

        $this->user = actingUserWithPermissions([
            'users.view',
            'users.create',
            'users.update',
        ]);

        $this->campus = Campus::factory()->create();
        $this->college = College::factory()->forCampus($this->campus)->create();
        $this->department = Department::factory()->forCollege($this->college)->create();
    });

    it('creates a faculty user with profile and role assignment', function () {
        Livewire::actingAs($this->user)
            ->test('pages::admin.users.index')
            ->call('openCreateModal')
            ->set('form.first_name', 'Lina')
            ->set('form.middle_name', 'M')
            ->set('form.last_name', 'Castro')
            ->set('form.email', 'lina.castro@example.test')
            ->set('form.roles', ['faculty'])
            ->set('form.type', 'faculty')
            ->set('form.campus_id', $this->campus->id)
            ->set('form.college_id', $this->college->id)
            ->set('form.department_id', $this->department->id)
            ->set('form.academic_rank', 'Instructor I')
            ->set('form.contactno', '09171234567')
            ->set('form.sex', 'Female')
            ->set('form.address', 'Indang, Cavite')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('createModal', false)
            ->assertDispatched('pg:eventRefresh-usersTable');

        $createdUser = User::query()->where('email', 'lina.castro@example.test')->first();

        expect($createdUser)->not->toBeNull()
            ->and($createdUser->hasRole('faculty'))->toBeTrue()
            ->and($createdUser->facultyProfile)->not->toBeNull()
            ->and($createdUser->facultyProfile->department_id)->toBe($this->department->id)
            ->and($createdUser->facultyProfile->academic_rank)->toBe('Instructor I');
    });

    it('downgrades a faculty user to standard and archives linked profiles', function () {
        $managedUser = User::factory()->faculty()->create();

        Livewire::actingAs($this->user)
            ->test('pages::admin.users.show', ['user' => $managedUser])
            ->call('startEditing')
            ->set('form.type', 'standard')
            ->set('form.roles', ['deptAdmin'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('isEditing', false);

        expect($managedUser->fresh()->facultyProfile)->toBeNull()
            ->and($managedUser->fresh()->employeeProfile)->toBeNull();
    });

    it('updates assignment context when changing campus college and department', function () {
        $newCampus = Campus::factory()->create();
        $newCollege = College::factory()->forCampus($newCampus)->create();
        $newDepartment = Department::factory()->forCollege($newCollege)->create();
        $managedUser = User::factory()->faculty()->create();

        Livewire::actingAs($this->user)
            ->test('pages::admin.users.show', ['user' => $managedUser])
            ->call('startEditing')
            ->set('form.campus_id', $newCampus->id)
            ->set('form.college_id', $newCollege->id)
            ->set('form.department_id', $newDepartment->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('isEditing', false)
            ->assertSet('form.campus_id', $newCampus->id)
            ->assertSet('form.college_id', $newCollege->id)
            ->assertSet('form.department_id', $newDepartment->id);

        expect($managedUser->fresh()->facultyProfile)->not->toBeNull()
            ->and($managedUser->fresh()->facultyProfile->campus_id)->toBe($newCampus->id)
            ->and($managedUser->fresh()->facultyProfile->college_id)->toBe($newCollege->id)
            ->and($managedUser->fresh()->facultyProfile->department_id)->toBe($newDepartment->id);
    });

    it('preserves both profiles for dual-role users when saving', function () {
        $managedUser = User::factory()->dualRole()->create();

        Livewire::actingAs($this->user)
            ->test('pages::admin.users.show', ['user' => $managedUser])
            ->call('startEditing')
            ->set('form.type', 'dual')
            ->set('form.roles', ['deptAdmin', 'faculty'])
            ->set('form.academic_rank', 'Associate Professor I')
            ->set('form.position', 'Department Chair')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('isEditing', false);

        $freshUser = $managedUser->fresh(['facultyProfile', 'employeeProfile']);

        expect($freshUser->facultyProfile)->not->toBeNull()
            ->and($freshUser->employeeProfile)->not->toBeNull()
            ->and($freshUser->facultyProfile->academic_rank)->toBe('Associate Professor I')
            ->and($freshUser->employeeProfile->position)->toBe('Department Chair');
    });

    it('renders account timestamps in Philippine time on the user detail page', function () {
        $managedUser = User::factory()->faculty()->create();

        DB::table('users')->where('id', $managedUser->id)->update([
            'created_at' => '2026-04-23 10:51:41',
            'updated_at' => '2026-04-23 11:31:21',
        ]);

        Livewire::actingAs($this->user)
            ->test('pages::admin.users.show', ['user' => $managedUser->fresh()])
            ->assertSee('Apr 23, 2026 10:51 AM')
            ->assertSee('Apr 23, 2026 11:31 AM');
    });
});
