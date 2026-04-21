<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

describe('assignments management', function () {
    beforeEach(function () {
        Role::findOrCreate('superAdmin', 'web');
    });

    it('super admin can sync direct permissions from the assignments page', function () {
        $actingUser = User::factory()->create();
        $actingUser->assignRole('superAdmin');

        $managedUser = User::factory()->create();

        $role = Role::findOrCreate('deptAdmin', 'web');
        $permission = Permission::findOrCreate('assignments.manage', 'web');

        Livewire::actingAs($actingUser)
            ->test('pages::admin.assignments.index')
            ->set('selectedUserId', $managedUser->id)
            ->set('userRoles', [$role->name])
            ->set('userPermissions', [$permission->name])
            ->call('saveUserAssignments')
            ->assertHasNoErrors();

        expect($managedUser->fresh()->hasRole($role->name))->toBeTrue()
            ->and($managedUser->fresh()->hasDirectPermission($permission->name))->toBeTrue();
    });

    it('user edit page syncs direct permissions when saving', function () {
        $actingUser = User::factory()->create();
        $actingUser->assignRole('superAdmin');

        $managedUser = User::factory()->create();
        Role::findOrCreate('faculty', 'web');
        $managedUser->assignRole('faculty');

        $permission = Permission::findOrCreate('permissions.view', 'web');

        Livewire::actingAs($actingUser)
            ->test('pages::admin.users.show', ['user' => $managedUser])
            ->call('startEditing')
            ->set('form.direct_permissions', [$permission->name])
            ->call('save')
            ->assertHasNoErrors();

        expect($managedUser->fresh()->hasDirectPermission($permission->name))->toBeTrue();
    });
});
