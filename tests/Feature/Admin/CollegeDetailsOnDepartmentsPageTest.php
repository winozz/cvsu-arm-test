<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::findOrCreate('superAdmin', 'web');
});

test('departments page can update the current college details', function () {
    $user = User::factory()->create();
    $user->assignRole('superAdmin');

    $campus = Campus::factory()->create([
        'code' => 'CvSU-MAIN',
        'name' => 'Main Campus',
    ]);

    $college = College::factory()->forCampus($campus)->create([
        'code' => 'CAS',
        'name' => 'College of Arts and Sciences',
        'description' => 'Original college description.',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::admin.departments.index', ['campus' => $campus, 'college' => $college])
        ->call('editCollege')
        ->set('collegeForm.code', 'CASI')
        ->set('collegeForm.name', 'College of Arts, Sciences, and Innovation')
        ->set('collegeForm.description', 'Updated college description.')
        ->set('collegeForm.is_active', false)
        ->call('saveCollege')
        ->assertHasNoErrors();

    expect($college->fresh()->code)->toBe('CASI')
        ->and($college->fresh()->name)->toBe('College of Arts, Sciences, and Innovation')
        ->and($college->fresh()->description)->toBe('Updated college description.')
        ->and($college->fresh()->is_active)->toBeFalse();
});

test('save confirmation closes the college modal and can reopen on cancel', function () {
    $user = User::factory()->create();
    $user->assignRole('superAdmin');

    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create();

    Livewire::actingAs($user)
        ->test('pages::admin.departments.index', ['campus' => $campus, 'college' => $college])
        ->call('editCollege')
        ->assertSet('collegeModal', true)
        ->call('confirmSaveCollege')
        ->assertHasNoErrors()
        ->assertSet('collegeModal', false)
        ->call('reopenCollegeModal')
        ->assertSet('collegeModal', true);
});
