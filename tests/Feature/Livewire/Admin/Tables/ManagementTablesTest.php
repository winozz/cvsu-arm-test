<?php

use App\Livewire\Admin\Tables\PermissionsTable;
use App\Livewire\Admin\Tables\RolesTable;
use App\Livewire\Admin\Tables\UsersTable;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

describe('PermissionsTable', function () {
    beforeEach(function () {
        $this->user = actingUserWithPermissions([
            'permissions.update',
            'permissions.delete',
            'permissions.restore',
        ]);
    });

    it('configures outside filters, the export name, and formatted deleted at field', function () {
        $permission = Permission::findOrCreate('users.archive', 'web');
        $permission->delete();

        $component = Livewire::actingAs($this->user)->test(PermissionsTable::class);
        $setUp = $component->instance()->setUp();
        $fields = $component->instance()->fields()->fields;

        expect(config('livewire-powergrid.filter'))->toBe('outside')
            ->and($setUp[0]->fileName)->toBe('permissions-list')
            ->and($setUp[0]->type)->toBe([Exportable::TYPE_XLS, Exportable::TYPE_CSV])
            ->and($fields['deleted_at']($permission->fresh()))->toBe($permission->fresh()->deleted_at->format('d/m/Y H:i:s'));
    });

    it('builds edit, delete, and restore actions', function () {
        $permission = Permission::findOrCreate('permissions.audit', 'web');

        $actions = Livewire::actingAs($this->user)
            ->test(PermissionsTable::class)
            ->instance()
            ->actions($permission);

        expect(collect($actions)->pluck('action')->all())->toBe([
            'edit-permission',
            'delete-permission',
            'restore-permission',
        ])->and($actions[0]->attributes['wire:click'])->toContain('editPermission')
            ->and($actions[1]->attributes['wire:click'])->toContain('confirmDeletePermission')
            ->and($actions[2]->attributes['wire:click'])->toContain('confirmRestorePermission');
    });

    it('soft deletes and restores permissions through table actions', function () {
        $permission = Permission::findOrCreate('permissions.cleanup', 'web');

        Livewire::actingAs($this->user)
            ->test(PermissionsTable::class)
            ->call('deletePermission', $permission->id)
            ->assertDispatched('pg:eventRefresh-permissionsTable');

        expect($permission->fresh()->trashed())->toBeTrue();

        Livewire::actingAs($this->user)
            ->test(PermissionsTable::class)
            ->call('restorePermission', $permission->id)
            ->assertDispatched('pg:eventRefresh-permissionsTable');

        expect($permission->fresh()->trashed())->toBeFalse();
    });
});

describe('RolesTable', function () {
    beforeEach(function () {
        $this->user = actingUserWithPermissions([
            'roles.update',
            'roles.delete',
            'roles.restore',
        ]);
    });

    it('formats the deleted at field and builds role actions', function () {
        $role = Role::findOrCreate('registrarAdmin', 'web');
        $role->delete();

        $component = Livewire::actingAs($this->user)->test(RolesTable::class)->instance();
        $fields = $component->fields()->fields;
        $actions = $component->actions($role->fresh());

        expect($fields['deleted_at']($role->fresh()))->toBe($role->fresh()->deleted_at->format('d/m/Y'))
            ->and(collect($actions)->pluck('action')->all())->toBe([
                'edit-role',
                'delete-role',
                'restore-role',
            ]);
    });

    it('soft deletes and restores roles through table actions', function () {
        $role = Role::findOrCreate('assistantRegistrar', 'web');

        Livewire::actingAs($this->user)
            ->test(RolesTable::class)
            ->call('deleteRole', $role->id)
            ->assertDispatched('pg:eventRefresh-rolesTable');

        expect($role->fresh()->trashed())->toBeTrue();

        Livewire::actingAs($this->user)
            ->test(RolesTable::class)
            ->call('restoreRole', $role->id)
            ->assertDispatched('pg:eventRefresh-rolesTable');

        expect($role->fresh()->trashed())->toBeFalse();
    });
});

describe('UsersTable', function () {
    beforeEach(function () {
        ensureRoles(['superAdmin', 'collegeAdmin', 'deptAdmin', 'faculty']);

        $this->user = actingUserWithPermissions([
            'users.view',
            'users.delete',
            'users.restore',
        ]);
    });

    it('limits the datasource to managed roles and honors soft delete state', function () {
        $managedUser = User::factory()->faculty()->create();
        $standardUser = User::factory()->create();
        $trashedManagedUser = User::factory()->deptAdmin()->create();
        $trashedManagedUser->delete();

        $component = Livewire::actingAs($this->user)->test(UsersTable::class);

        expect($component->instance()->datasource()->pluck('id')->all())
            ->toContain($managedUser->id)
            ->not->toContain($standardUser->id, $trashedManagedUser->id);

        $component->set('softDeletes', 'withTrashed');

        expect($component->instance()->datasource()->pluck('id')->all())
            ->toContain($managedUser->id, $trashedManagedUser->id)
            ->not->toContain($standardUser->id);
    });

    it('builds avatar, roles list, and position rank field values', function () {
        $avatarUser = User::factory()->create([
            'name' => 'John Sample',
            'avatar' => 'https://example.test/avatar.png',
        ]);
        $avatarUser->assignRole('superAdmin');

        $dualRoleUser = User::factory()->create(['name' => 'Mary Ann Stone']);
        $dualRoleUser->assignRole(['collegeAdmin', 'deptAdmin']);

        $facultyUser = User::factory()->faculty()->create([
            'name' => 'Jane Faculty',
        ]);
        $facultyUser->avatar = null;
        $facultyUser->save();

        $component = Livewire::actingAs($this->user)->test(UsersTable::class)->instance();
        $fields = $component->fields()->fields;

        expect($fields['avatar_view']($avatarUser->fresh()))->toContain('<img', 'https://example.test/avatar.png')
            ->and($fields['avatar_view']($facultyUser->fresh()))->toContain('JF')
            ->and($fields['roles_list']($dualRoleUser->fresh('roles')))->toContain('College Admin', 'Dept Admin')
            ->and($fields['position_rank']($facultyUser->fresh(['facultyProfile'])))->toContain('Instructor I');
    });

    it('switches action buttons between active and trashed users', function () {
        $activeUser = User::factory()->faculty()->create();
        $trashedUser = User::factory()->deptAdmin()->create();
        $trashedUser->delete();

        $component = Livewire::actingAs($this->user)->test(UsersTable::class)->instance();
        $activeActions = $component->actions($activeUser->fresh());
        $trashedActions = $component->actions($trashedUser->fresh());

        expect(collect($activeActions)->pluck('action')->all())->toBe(['view', 'delete'])
            ->and($activeActions[0]->attributes['href'])->toBe(route('admin.users.show', ['user' => $activeUser->id]))
            ->and($activeActions[1]->attributes['wire:click'])->toContain('confirmDelete')
            ->and(collect($trashedActions)->pluck('action')->all())->toBe(['restore'])
            ->and($trashedActions[0]->attributes['wire:click'])->toContain('confirmRestore');
    });
});
