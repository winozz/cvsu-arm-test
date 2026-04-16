<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

describe('admin department list page', function () {
    beforeEach(function () {
        Role::findOrCreate('superAdmin', 'web');
    });

    it('department list can create a department using the modal form', function () {
        $user = User::factory()->create();
        $user->assignRole('superAdmin');

        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create([
            'code' => 'CAS',
        ]);

        Livewire::actingAs($user)
            ->test('pages::admin.departments.index', ['campus' => $campus, 'college' => $college])
            ->call('openCreateDepartmentModal')
            ->set('departmentForm.code', 'CAS-REXT')
            ->set('departmentForm.name', 'Research and Extension Department')
            ->set('departmentForm.description', 'Created from the department list modal.')
            ->set('departmentForm.is_active', true)
            ->call('saveDepartment')
            ->assertHasNoErrors();

        $department = Department::query()
            ->where('college_id', $college->id)
            ->where('code', 'CAS-REXT')
            ->first();

        expect($department)->not->toBeNull()
            ->and($department->campus_id)->toBe($campus->id)
            ->and($department->name)->toBe('Research and Extension Department')
            ->and($department->is_active)->toBeTrue();
    });

    it('department list warns before creating a department with an exact existing code under the same college', function () {
        $user = User::factory()->create();
        $user->assignRole('superAdmin');

        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create([
            'code' => 'CEIT',
        ]);

        Department::factory()->forCollege($college)->create([
            'code' => 'CEIT-ACAD',
            'name' => 'Academic Programs Department',
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::admin.departments.index', ['campus' => $campus, 'college' => $college]);

        $component
            ->call('openCreateDepartmentModal')
            ->set('departmentForm.code', 'CEIT-ACAD')
            ->set('departmentForm.name', 'Alternate Academic Department')
            ->set('departmentForm.description', 'Potential duplicate entry.')
            ->call('confirmSaveDepartment')
            ->assertHasNoErrors()
            ->assertSet('departmentDuplicateConflictDetected', true)
            ->assertSet('departmentDuplicateConflicts', ['CEIT-ACAD - Academic Programs Department'])
            ->assertSet('departmentModal', false);

        expect(Department::query()
            ->where('college_id', $college->id)
            ->where('code', 'CEIT-ACAD')
            ->where('name', 'Alternate Academic Department')
            ->exists())->toBeFalse();

        $component
            ->call('saveDepartment')
            ->assertHasNoErrors();

        expect(Department::query()
            ->where('college_id', $college->id)
            ->where('code', 'CEIT-ACAD')
            ->where('name', 'Alternate Academic Department')
            ->exists())->toBeTrue();
    });

    it('department list warns and lists all similar existing entries for fuzzy code matches', function () {
        $user = User::factory()->create();
        $user->assignRole('superAdmin');

        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create([
            'code' => 'CEIT',
        ]);

        Department::factory()->forCollege($college)->create([
            'code' => 'CEIT-ACAD',
            'name' => 'Academic Programs Department',
        ]);

        Department::factory()->forCollege($college)->create([
            'code' => 'CEIT-ACADA',
            'name' => 'Academic Affairs Department',
        ]);

        Department::factory()->forCollege($college)->create([
            'code' => 'CEIT-ACAE',
            'name' => 'Academic Extension Department',
        ]);

        Livewire::actingAs($user)
            ->test('pages::admin.departments.index', ['campus' => $campus, 'college' => $college])
            ->call('openCreateDepartmentModal')
            ->set('departmentForm.code', 'CEIT-ACA')
            ->set('departmentForm.name', 'New Academic Unit')
            ->call('confirmSaveDepartment')
            ->assertHasNoErrors()
            ->assertSet('departmentDuplicateConflictDetected', true)
            ->assertSet('departmentDuplicateConflicts', [
                'CEIT-ACAD - Academic Programs Department',
                'CEIT-ACADA - Academic Affairs Department',
                'CEIT-ACAE - Academic Extension Department',
            ])
            ->assertSet('departmentModal', false);
    });

    it('department list can update a department using the modal form', function () {
        $user = User::factory()->create();
        $user->assignRole('superAdmin');

        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create([
            'code' => 'CAS-ACAD',
            'name' => 'Academic Programs Department',
            'description' => 'Original department description.',
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test('pages::admin.departments.index', ['campus' => $campus, 'college' => $college])
            ->call('openEditDepartmentModal', $department->id)
            ->set('departmentForm.code', 'CAS-STUD')
            ->set('departmentForm.name', 'Student Services Department')
            ->set('departmentForm.description', 'Updated department description.')
            ->set('departmentForm.is_active', false)
            ->call('saveDepartment')
            ->assertHasNoErrors();

        expect($department->fresh()->code)->toBe('CAS-STUD')
            ->and($department->fresh()->name)->toBe('Student Services Department')
            ->and($department->fresh()->description)->toBe('Updated department description.')
            ->and($department->fresh()->is_active)->toBeFalse();
    });

    it('department save confirmation closes the modal and can reopen on cancel', function () {
        $user = User::factory()->create();
        $user->assignRole('superAdmin');

        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create();

        Livewire::actingAs($user)
            ->test('pages::admin.departments.index', ['campus' => $campus, 'college' => $college])
            ->call('openEditDepartmentModal', $department->id)
            ->assertSet('departmentModal', true)
            ->call('confirmSaveDepartment')
            ->assertHasNoErrors()
            ->assertSet('departmentModal', false)
            ->call('reopenDepartmentModal')
            ->assertSet('departmentModal', true);
    });

    it('department create confirmation closes the modal and can reopen on cancel', function () {
        $user = User::factory()->create();
        $user->assignRole('superAdmin');

        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();

        Livewire::actingAs($user)
            ->test('pages::admin.departments.index', ['campus' => $campus, 'college' => $college])
            ->call('openCreateDepartmentModal')
            ->assertSet('departmentModal', true)
            ->assertSet('isEditingDepartment', false)
            ->set('departmentForm.code', 'NEW-DEPT')
            ->set('departmentForm.name', 'New Department')
            ->call('confirmSaveDepartment')
            ->assertHasNoErrors()
            ->assertSet('departmentModal', false)
            ->call('reopenDepartmentModal')
            ->assertSet('departmentModal', true);
    });
});
