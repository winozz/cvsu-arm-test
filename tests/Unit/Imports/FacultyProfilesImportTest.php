<?php

use App\Imports\FacultyProfilesImport;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('FacultyProfilesImport', function () {
    it('imports faculty only within a college admin scope', function () {
        ensureRoles(['faculty', 'collegeAdmin']);

        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create();

        $manager = User::factory()->collegeAdmin()->create();
        $managerProfile = EmployeeProfile::factory()->forDepartment($department)->create([
            'user_id' => $manager->id,
            'department_id' => null,
        ]);

        $rows = new Collection([
            collect([
                'first_name' => 'Aira',
                'middle_name' => 'M',
                'last_name' => 'Lopez',
                'email' => 'aira.lopez@example.test',
                'department_id' => $department->id,
                'academic_rank' => 'Instructor I',
            ]),
        ]);

        (new FacultyProfilesImport($managerProfile, $manager->id))->collection($rows);

        $user = User::query()->where('email', 'aira.lopez@example.test')->first();

        expect($user)->not->toBeNull()
            ->and($user->facultyProfile)->not->toBeNull()
            ->and($user->facultyProfile->college_id)->toBe($college->id)
            ->and($user->facultyProfile->department_id)->toBe($department->id);
    });

    it('rejects faculty import outside a department admin scope', function () {
        ensureRoles(['faculty', 'deptAdmin']);

        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create();
        $otherDepartment = Department::factory()->forCollege($college)->create();

        $manager = User::factory()->deptAdmin()->create();
        $managerProfile = EmployeeProfile::factory()->forDepartment($department)->create([
            'user_id' => $manager->id,
        ]);

        $rows = new Collection([
            collect([
                'first_name' => 'Kaye',
                'last_name' => 'Ramos',
                'email' => 'kaye.ramos@example.test',
                'department_id' => $otherDepartment->id,
            ]),
        ]);

        expect(fn () => (new FacultyProfilesImport($managerProfile, $manager->id))->collection($rows))
            ->toThrow(ValidationException::class);

        expect(User::query()->where('email', 'kaye.ramos@example.test')->exists())->toBeFalse()
            ->and(FacultyProfile::query()->where('email', 'kaye.ramos@example.test')->exists())->toBeFalse();
    });
});
