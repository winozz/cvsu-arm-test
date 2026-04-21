<?php

use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('User model', function () {
    beforeEach(function () {
        ensureRoles(['superAdmin', 'collegeAdmin', 'deptAdmin', 'faculty']);
        collect([
            'campuses.view',
            'departments.view',
            'faculty_schedules.view',
            'schedules.assign',
        ])->each(fn (string $permission) => Permission::findOrCreate($permission, 'web'));
    });

    it('defines the expected fillable attributes and relationships', function () {
        $user = User::factory()->faculty()->create();
        EmployeeProfile::factory()->create(['user_id' => $user->id]);

        expect((new User())->getFillable())->toBe([
            'name',
            'email',
            'password',
            'email_verified_at',
            'google_id',
            'avatar',
            'is_active',
        ])->and($user->facultyProfile)->not->toBeNull()
            ->and($user->employeeProfile)->not->toBeNull();
    });

    it('builds initials from the first two name parts', function () {
        $user = User::factory()->make(['name' => 'Maria Clara Santos']);

        expect($user->initials())->toBe('MC');
    });

    it('returns false for google sign in when the user has no supported access', function () {
        $user = User::factory()->create();

        expect($user->canUseGoogleSignIn())->toBeFalse();
    });

    it('requires a matching faculty profile email for faculty google sign in', function () {
        $user = User::factory()->create();
        $user->assignRole('faculty');
        $user->givePermissionTo('faculty_schedules.view');

        expect($user->canUseGoogleSignIn())->toBeFalse();

        FacultyProfile::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        expect($user->fresh()->canUseGoogleSignIn())->toBeTrue();
    });

    it('resolves dashboard routes using the configured priority order', function () {
        $user = User::factory()->create();
        $user->assignRole(['faculty', 'deptAdmin']);
        $user->givePermissionTo(['faculty_schedules.view', 'schedules.assign']);

        FacultyProfile::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        EmployeeProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        expect($user->fresh()->dashboardRoute())->toBe('department-admin.dashboard');

        $user->assignRole('collegeAdmin');
        $user->givePermissionTo('departments.view');

        expect($user->fresh()->dashboardRoute())->toBe('college-admin.dashboard');

        $user->assignRole('superAdmin');
        $user->givePermissionTo('campuses.view');

        expect($user->fresh()->dashboardRoute())->toBe('admin.dashboard');
    });

    it('syncs google profile data onto the user record', function () {
        $user = User::factory()->create();

        $user->syncGoogleProfile('google-456', 'https://example.test/me.png');

        $fresh = $user->fresh();

        expect($fresh->google_id)->toBe('google-456')
            ->and($fresh->avatar)->toBe('https://example.test/me.png')
            ->and($fresh->email_verified_at)->not->toBeNull();
    });
});
