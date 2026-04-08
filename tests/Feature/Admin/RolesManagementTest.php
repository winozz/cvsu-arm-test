<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::findOrCreate('superAdmin', 'web');
});

test('roles page can create a role with permissions selected as string ids', function () {
    $user = User::factory()->create();
    $user->assignRole('superAdmin');

    $permission = Permission::findOrCreate('roles.view', 'web');

    Livewire::actingAs($user)
        ->test('pages::admin.roles.index')
        ->call('openCreateModal')
        ->set('form.name', 'registrarAdmin')
        ->set('form.guard_name', 'web')
        ->set('form.permissions', [(string) $permission->id])
        ->call('save')
        ->assertHasNoErrors();

    $role = Role::where('name', 'registrarAdmin')->first();

    expect($role)->not->toBeNull()
        ->and($role->hasPermissionTo($permission))->toBeTrue();
});

test('roles page can update a role with permissions selected as string ids', function () {
    $user = User::factory()->create();
    $user->assignRole('superAdmin');

    $originalPermission = Permission::findOrCreate('roles.view', 'web');
    $updatedPermission = Permission::findOrCreate('roles.edit', 'web');

    $role = Role::findOrCreate('registrarAdmin', 'web');
    $role->syncPermissions([$originalPermission]);

    Livewire::actingAs($user)
        ->test('pages::admin.roles.index')
        ->call('openEditModal', $role->id)
        ->set('form.permissions', [(string) $updatedPermission->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($role->fresh()->hasPermissionTo($updatedPermission))->toBeTrue()
        ->and($role->fresh()->hasPermissionTo($originalPermission))->toBeFalse();
});
