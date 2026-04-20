<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('AuthServiceProvider gates', function () {
    beforeEach(function () {
        ensureRoles(['superAdmin', 'deptAdmin']);
        Permission::findOrCreate('rooms.view', 'web');
    });

    it('allows rooms.menu when the user has rooms permission and both college and department assignments', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create();
        $user = User::factory()->create();
        $user->assignRole('deptAdmin');
        $user->givePermissionTo('rooms.view');

        EmployeeProfile::factory()->forDepartment($department)->create([
            'user_id' => $user->id,
        ]);

        expect(Gate::forUser($user)->allows('rooms.menu'))->toBeTrue();
    });

    it('denies rooms.menu when the user is missing college or department assignments', function () {
        $user = User::factory()->create();
        $user->assignRole('deptAdmin');
        $user->givePermissionTo('rooms.view');

        expect(Gate::forUser($user)->allows('rooms.menu'))->toBeFalse();
    });

    it('allows rooms.menu for super admins through gate before', function () {
        $user = User::factory()->superAdmin()->create();

        expect(Gate::forUser($user)->allows('rooms.menu'))->toBeTrue();
    });
});
