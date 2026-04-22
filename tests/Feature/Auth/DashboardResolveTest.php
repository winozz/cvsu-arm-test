<?php

use App\Models\Permission;
use App\Models\User;
use Spatie\Permission\Models\Role;

describe('dashboard resolver', function () {
    beforeEach(function () {
        collect(['superAdmin', 'collegeAdmin', 'deptAdmin', 'faculty'])
            ->each(fn (string $role) => Role::findOrCreate($role, 'web'));

        collect([
            'campuses.view',
            'departments.view',
            'schedules.assign',
            'faculty_schedules.view',
        ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
    });

    it('guest users are redirected to login when opening dashboard resolver', function () {
        $this->get(route('dashboard.resolve'))
            ->assertRedirect(route('login'));
    });

    it('dashboard resolver uses configured role priority for multi role users', function () {
        $user = User::factory()->create();
        $user->assignRole(['faculty', 'deptAdmin']);
        $user->givePermissionTo(['faculty_schedules.view', 'schedules.assign']);

        $this->actingAs($user)
            ->get(route('dashboard.resolve'))
            ->assertRedirect(route('dashboard.department'));
    });

    it('dashboard resolver allows dept admins with permission only', function () {
        $user = User::factory()->create();
        $user->assignRole(['faculty', 'deptAdmin']);
        $user->givePermissionTo(['faculty_schedules.view', 'schedules.assign']);

        $this->actingAs($user)
            ->get(route('dashboard.resolve'))
            ->assertRedirect(route('dashboard.department'));
    });

    it('dashboard resolver allows college access with permission only', function () {
        $user = User::factory()->create();
        $user->assignRole('faculty');
        $user->givePermissionTo('departments.view');

        $this->actingAs($user)
            ->get(route('dashboard.resolve'))
            ->assertRedirect(route('dashboard.college'));
    });

    it('dashboard resolver returns forbidden for users without valid dashboard', function () {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.resolve'))
            ->assertForbidden();
    });
});
