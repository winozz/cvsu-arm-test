<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

test('college admin can update their current college details from the departments page', function () {
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

    Department::factory()->forCollege($college)->create([
        'code' => 'CAS-BASE',
        'name' => 'Base Department',
    ]);

    $user = User::factory()->collegeAdmin()->create();

    Livewire::actingAs($user)
        ->test('pages::college-admin.departments.index')
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
