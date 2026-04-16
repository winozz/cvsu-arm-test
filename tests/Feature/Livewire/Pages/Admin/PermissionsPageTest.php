<?php

use App\Models\Permission;
use Livewire\Livewire;

describe('admin permissions page', function () {
    beforeEach(function () {
        $this->user = actingUserWithPermissions([
            'permissions.view',
            'permissions.create',
            'permissions.update',
        ]);
    });

    it('creates a permission from the modal form', function () {
        Livewire::actingAs($this->user)
            ->test('pages::admin.permissions.index')
            ->call('openCreateModal')
            ->set('form.name', 'users.archive')
            ->set('form.guard_name', 'web')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('permissionModal', false)
            ->assertDispatched('pg:eventRefresh-permissionsTable');

        expect(Permission::query()->where('name', 'users.archive')->where('guard_name', 'web')->exists())->toBeTrue();
    });

    it('updates a permission from the modal form', function () {
        $permission = Permission::findOrCreate('users.export', 'web');

        Livewire::actingAs($this->user)
            ->test('pages::admin.permissions.index')
            ->call('openEditModal', $permission->id)
            ->set('form.name', 'users.export.bulk')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('permissionModal', false)
            ->assertDispatched('pg:eventRefresh-permissionsTable');

        expect($permission->fresh()->name)->toBe('users.export.bulk');
    });
});
