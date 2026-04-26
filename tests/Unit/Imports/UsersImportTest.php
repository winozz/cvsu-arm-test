<?php

use App\Imports\UsersImport;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('UsersImport', function () {
    beforeEach(function () {
        ensureRoles(['faculty', 'deptAdmin', 'superAdmin']);
        Permission::findOrCreate('rooms.view', 'web');
    });

    it('restores soft deleted user records and syncs dual profiles from the imported assignment', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create();

        $user = User::factory()->create([
            'name' => 'Old Record',
            'email' => 'mia@example.test',
        ]);

        FacultyProfile::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ])->delete();

        EmployeeProfile::factory()->create([
            'user_id' => $user->id,
        ])->delete();

        $user->delete();

        $rows = new Collection([
            collect([
                'first_name' => 'Mia',
                'middle_name' => 'S',
                'last_name' => 'Torres',
                'email' => 'mia@example.test',
                'type' => 'dual',
                'roles' => 'deptAdmin, faculty',
                'direct_permissions' => 'rooms.view',
                'department_id' => $department->id,
                'academic_rank' => 'Instructor II',
                'position' => 'Coordinator',
                'is_active' => 'yes',
            ]),
        ]);

        (new UsersImport())->collection($rows);

        $freshUser = User::query()->where('email', 'mia@example.test')->first();

        expect($freshUser)->not->toBeNull()
            ->and($freshUser->trashed())->toBeFalse()
            ->and($freshUser->name)->toBe('Mia S Torres')
            ->and($freshUser->hasRole('deptAdmin'))->toBeTrue()
            ->and($freshUser->hasRole('faculty'))->toBeTrue()
            ->and($freshUser->hasDirectPermission('rooms.view'))->toBeTrue();

        $freshFacultyProfile = FacultyProfile::withTrashed()->where('user_id', $freshUser->id)->latest('id')->first();
        $freshEmployeeProfile = EmployeeProfile::withTrashed()->where('user_id', $freshUser->id)->latest('id')->first();

        expect($freshFacultyProfile)->not->toBeNull()
            ->and($freshFacultyProfile->trashed())->toBeFalse()
            ->and($freshFacultyProfile->department_id)->toBe($department->id)
            ->and($freshFacultyProfile->college_id)->toBe($college->id)
            ->and($freshFacultyProfile->campus_id)->toBe($campus->id)
            ->and($freshFacultyProfile->academic_rank)->toBe('Instructor II')
            ->and($freshEmployeeProfile)->not->toBeNull()
            ->and($freshEmployeeProfile->trashed())->toBeFalse()
            ->and($freshEmployeeProfile->position)->toBe('Coordinator')
            ->and($freshEmployeeProfile->department_id)->toBe($department->id);
    });

    it('archives linked academic profiles when an imported row downgrades a user to standard', function () {
        $user = User::factory()->dualRole()->create([
            'email' => 'archived@example.test',
        ]);

        $rows = new Collection([
            collect([
                'first_name' => 'Archived',
                'last_name' => 'User',
                'email' => 'archived@example.test',
                'type' => 'standard',
                'roles' => 'superAdmin',
                'is_active' => 'true',
            ]),
        ]);

        (new UsersImport())->collection($rows);

        $freshUser = $user->fresh();

        expect($freshUser->hasRole('superAdmin'))->toBeTrue()
            ->and($freshUser->facultyProfile)->toBeNull()
            ->and($freshUser->employeeProfile)->toBeNull()
            ->and(FacultyProfile::withTrashed()->where('user_id', $user->id)->exists())->toBeTrue()
            ->and(EmployeeProfile::withTrashed()->where('user_id', $user->id)->exists())->toBeTrue();
    });
});
