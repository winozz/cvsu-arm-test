<?php

use App\Livewire\Tables\Admin\FacultyProfilesTable;
use App\Models\College;
use App\Models\Department;
use App\Models\FacultyProfile;
use Livewire\Livewire;

describe('FacultyProfilesTable', function () {
    beforeEach(function () {
        $this->user = actingUserWithPermissions([
            'faculty_profiles.view',
            'faculty_profiles.delete',
            'faculty_profiles.restore',
        ], ['deptAdmin']);
        $this->department = Department::factory()->create();
    });

    it('exposes relation search mappings and computed field values', function () {
        $profile = FacultyProfile::factory()->forDepartment($this->department)->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'academic_rank' => null,
        ]);

        $component = Livewire::actingAs($this->user)->test(FacultyProfilesTable::class)->instance();
        $fields = $component->fields()->fields;

        expect($component->relationSearch())->toBe([
            'campus' => ['name'],
            'college' => ['name'],
            'department' => ['name'],
            'user' => ['name', 'email'],
        ])->and($fields['full_name']($profile))->toBe('Jane Doe')
            ->and($fields['academic_rank']($profile))->toBe('-')
            ->and($fields['campus_name']($profile))->toBe($profile->campus->name)
            ->and($fields['college_name']($profile))->toBe($profile->college->name)
            ->and($fields['department_name']($profile))->toBe($profile->department->name);
    });

    it('builds view, delete, and restore actions with the expected handlers', function () {
        $profile = FacultyProfile::factory()->forDepartment($this->department)->create();

        $actions = Livewire::actingAs($this->user)
            ->test(FacultyProfilesTable::class)
            ->instance()
            ->actions($profile);

        expect(collect($actions)->pluck('action')->all())->toBe([
            'view-faculty',
            'delete-faculty',
        ])->and($actions[0]->attributes['href'])->toBe(route('faculty-profiles.show', [
            'facultyProfile' => $profile->id,
        ]))
            ->and($actions[1]->attributes['wire:click'])->toContain('confirmDeleteFaculty');

        $profile->delete();

        $restoreActions = Livewire::actingAs($this->user)
            ->test(FacultyProfilesTable::class)
            ->instance()
            ->actions($profile->fresh());

        expect(collect($restoreActions)->pluck('action')->all())->toBe(['restore-faculty'])
            ->and($restoreActions[0]->attributes['wire:click'])->toContain('confirmRestoreFaculty');
    });

    it('soft deletes and restores faculty profiles through table actions', function () {
        $profile = FacultyProfile::factory()->forDepartment($this->department)->create();

        Livewire::actingAs($this->user)
            ->test(FacultyProfilesTable::class)
            ->call('deleteFaculty', $profile->id)
            ->assertDispatched('pg:eventRefresh-facultyProfilesTable');

        expect($profile->fresh()->trashed())->toBeTrue();

        Livewire::actingAs($this->user)
            ->test(FacultyProfilesTable::class)
            ->call('restoreFaculty', $profile->id)
            ->assertDispatched('pg:eventRefresh-facultyProfilesTable');

        expect($profile->fresh()->trashed())->toBeFalse();
    });

    it('lists faculty profiles from all departments when view permission is assigned', function () {
        $college = $this->department->college;
        $otherDepartmentInCollege = Department::factory()->forCollege($college)->create();
        $otherCollege = College::factory()->forCampus($college->campus)->create();
        $departmentOutsideCollege = Department::factory()->forCollege($otherCollege)->create();

        $facultyInPrimaryDepartment = FacultyProfile::factory()->forDepartment($this->department)->create();
        $facultyInSameCollege = FacultyProfile::factory()->forDepartment($otherDepartmentInCollege)->create();
        $facultyOutsideCollege = FacultyProfile::factory()->forDepartment($departmentOutsideCollege)->create();

        $ids = Livewire::actingAs($this->user)
            ->test(FacultyProfilesTable::class)
            ->instance()
            ->datasource()
            ->pluck('id')
            ->all();

        expect($ids)->toContain($facultyInPrimaryDepartment->id, $facultyInSameCollege->id)
            ->toContain($facultyOutsideCollege->id);
    });
});
