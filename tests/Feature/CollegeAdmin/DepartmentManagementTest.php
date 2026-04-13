<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

test('college admin can create a department for their current college using the modal form', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create([
        'code' => 'CAS',
    ]);

    Department::factory()->forCollege($college)->create([
        'code' => 'CAS-BASE',
        'name' => 'Base Department',
    ]);

    $user = User::factory()->collegeAdmin()->create();

    Livewire::actingAs($user)
        ->test('pages::college-admin.departments.index')
        ->call('openCreateDepartmentModal')
        ->set('departmentForm.code', 'CAS-REXT')
        ->set('departmentForm.name', 'Research and Extension Department')
        ->set('departmentForm.description', 'Created from the college admin department list modal.')
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

test('college admin sees duplicate warning before creating a similar department in their current college', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create([
        'code' => 'CEIT',
    ]);

    Department::factory()->forCollege($college)->create([
        'code' => 'CEIT-ACAD',
        'name' => 'Academic Programs Department',
    ]);

    $user = User::factory()->collegeAdmin()->create();

    Livewire::actingAs($user)
        ->test('pages::college-admin.departments.index')
        ->call('openCreateDepartmentModal')
        ->set('departmentForm.code', 'CEIT-ACAD')
        ->set('departmentForm.name', 'Alternate Academic Department')
        ->set('departmentForm.description', 'Potential duplicate entry.')
        ->call('confirmSaveDepartment')
        ->assertHasNoErrors()
        ->assertSet('departmentDuplicateConflictDetected', true)
        ->assertSet('departmentDuplicateConflicts', ['CEIT-ACAD - Academic Programs Department'])
        ->assertSet('departmentModal', false);
});

test('college admin can update a department in their current college using the modal form', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create();
    $department = Department::factory()->forCollege($college)->create([
        'code' => 'CAS-ACAD',
        'name' => 'Academic Programs Department',
        'description' => 'Original department description.',
        'is_active' => true,
    ]);

    $user = User::factory()->collegeAdmin()->create();

    Livewire::actingAs($user)
        ->test('pages::college-admin.departments.index')
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

test('college admin can soft delete a department from the departments table actions', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create();
    $department = Department::factory()->forCollege($college)->create([
        'code' => 'CAS-DEL',
        'name' => 'Department To Delete',
    ]);

    $user = User::factory()->collegeAdmin()->create();

    Livewire::actingAs($user)
        ->test('pages::college-admin.departments.index')
        ->call('deleteDepartment', $department->id)
        ->assertHasNoErrors();

    expect($department->fresh())->not->toBeNull()
        ->and($department->fresh()->trashed())->toBeTrue();
});

test('college admin can restore a trashed department from the departments table actions', function () {
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create();
    $department = Department::factory()->forCollege($college)->create([
        'code' => 'CAS-RESTORE',
        'name' => 'Department To Restore',
    ]);
    $department->delete();

    $user = User::factory()->collegeAdmin()->create();

    Livewire::actingAs($user)
        ->test('pages::college-admin.departments.index')
        ->call('restoreDepartment', $department->id)
        ->assertHasNoErrors();

    expect($department->fresh())->not->toBeNull()
        ->and($department->fresh()->trashed())->toBeFalse();
});
