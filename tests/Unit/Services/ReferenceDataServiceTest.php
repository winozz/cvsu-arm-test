<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Services\ReferenceDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('ReferenceDataService', function () {
    beforeEach(function () {
        cache()->flush();
        $this->service = app(ReferenceDataService::class);
    });

    it('caches active campus options until the cache is cleared', function () {
        $alpha = Campus::factory()->create(['name' => 'Alpha Campus']);
        $omega = Campus::factory()->create(['name' => 'Omega Campus']);
        Campus::factory()->inactive()->create(['name' => 'Hidden Campus']);

        expect($this->service->campuses())->toBe([
            ['label' => 'Alpha Campus', 'value' => $alpha->id],
            ['label' => 'Omega Campus', 'value' => $omega->id],
        ]);

        $alpha->update(['name' => 'Gamma Campus']);

        expect($this->service->campuses())->toBe([
            ['label' => 'Alpha Campus', 'value' => $alpha->id],
            ['label' => 'Omega Campus', 'value' => $omega->id],
        ]);

        $this->service->clearCache();

        expect($this->service->campuses())->toBe([
            ['label' => 'Gamma Campus', 'value' => $alpha->id],
            ['label' => 'Omega Campus', 'value' => $omega->id],
        ]);
    });

    it('returns accessor labels for active roles and permissions only', function () {
        $activeRole = Role::findOrCreate('deptAdmin', 'web');
        $deletedRole = Role::findOrCreate('retiredRole', 'web');
        $deletedRole->delete();

        $activePermission = Permission::findOrCreate('faculty_profiles.view', 'web');
        $deletedPermission = Permission::findOrCreate('users.archive', 'web');
        $deletedPermission->delete();

        expect($this->service->roles())->toBe([
            ['label' => $activeRole->display_name, 'value' => $activeRole->name],
        ])->and($this->service->permissions())->toBe([
            ['label' => $activePermission->display_name, 'value' => $activePermission->name],
        ])->and($this->service->rolesAsNames())->toBe([
            ['label' => $activeRole->name, 'value' => $activeRole->name],
        ])->and($this->service->permissionsAsNames())->toBe([
            ['label' => $activePermission->name, 'value' => $activePermission->name],
        ]);
    });

    it('caches campus and college scoped options independently', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create(['name' => 'Alpha College']);
        $department = Department::factory()->forCollege($college)->create(['name' => 'Research Department']);

        expect($this->service->collegesForCampus($campus->id))->toBe([
            ['label' => 'Alpha College', 'value' => $college->id],
        ])->and($this->service->departmentsForCollege($college->id))->toBe([
            ['label' => 'Research Department', 'value' => $department->id],
        ]);

        $college->update(['name' => 'Beta College']);
        $department->update(['name' => 'Systems Department']);

        expect($this->service->collegesForCampus($campus->id))->toBe([
            ['label' => 'Alpha College', 'value' => $college->id],
        ])->and($this->service->departmentsForCollege($college->id))->toBe([
            ['label' => 'Research Department', 'value' => $department->id],
        ]);

        $this->service->clearCampusCache($campus->id);
        $this->service->clearCollegeCache($college->id);

        expect($this->service->collegesForCampus($campus->id))->toBe([
            ['label' => 'Beta College', 'value' => $college->id],
        ])->and($this->service->departmentsForCollege($college->id))->toBe([
            ['label' => 'Systems Department', 'value' => $department->id],
        ]);
    });
});
