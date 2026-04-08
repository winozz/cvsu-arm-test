<?php

use App\Models\Campus;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::findOrCreate('superAdmin', 'web');
});

test('colleges page can update the current campus details', function () {
    $user = User::factory()->create();
    $user->assignRole('superAdmin');

    $campus = Campus::factory()->create([
        'code' => 'CvSU-MAIN',
        'name' => 'Main Campus',
        'description' => 'Original campus description.',
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test('pages::admin.colleges.index', ['campus' => $campus])
        ->call('editCampus')
        ->set('form.code', 'CvSU-IMUS')
        ->set('form.name', 'Imus Campus')
        ->set('form.description', 'Updated campus description.')
        ->set('form.is_active', false)
        ->call('saveCampus')
        ->assertHasNoErrors();

    expect($campus->fresh()->code)->toBe('CvSU-IMUS')
        ->and($campus->fresh()->name)->toBe('Imus Campus')
        ->and($campus->fresh()->description)->toBe('Updated campus description.')
        ->and($campus->fresh()->is_active)->toBeFalse();
});

test('save confirmation closes the campus modal and can reopen on cancel', function () {
    $user = User::factory()->create();
    $user->assignRole('superAdmin');

    $campus = Campus::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::admin.colleges.index', ['campus' => $campus])
        ->call('editCampus')
        ->assertSet('campusModal', true)
        ->call('confirmSaveCampus')
        ->assertHasNoErrors()
        ->assertSet('campusModal', false)
        ->call('reopenCampusModal')
        ->assertSet('campusModal', true);
});
