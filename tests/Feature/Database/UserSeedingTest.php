<?php

use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\CampusSeeder;
use Database\Seeders\CollegeSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\GeneratedUserSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;

describe('user seeders', function () {
    beforeEach(function () {
        $this->seed([
            CampusSeeder::class,
            CollegeSeeder::class,
            DepartmentSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    });

    it('keeps UserSeeder limited to legacy accounts', function () {
        $this->seed(UserSeeder::class);

        expect(User::query()->count())->toBe(5)
            ->and(User::query()->pluck('email')->all())->toContain(
                'tristan.sangangbayan@cvsu.edu.ph',
                'dlxks.sangangbayan@gmail.com',
                'ljohnmark9@gmail.com',
                'sky.shira7@gmail.com',
                'sangangbayant@gmail.com',
            )
            ->and(User::query()->where('email', 'like', 'generated.%')->count())->toBe(0);
    });

    it('seeds generated users per department and college with overlapping role combinations', function () {
        $this->seed([
            UserSeeder::class,
            GeneratedUserSeeder::class,
        ]);

        $departments = Department::query()->where('is_active', true)->get();
        $colleges = College::query()
            ->whereIn('id', $departments->pluck('college_id')->unique())
            ->where('is_active', true)
            ->get();

        expect(User::query()->where('email', 'like', 'generated.faculty.%')->count())
            ->toBe($departments->count() * 10)
            ->and(User::query()->where('email', 'like', 'generated.dept-admin.%')->count())
            ->toBe($departments->count() * 5)
            ->and(User::query()->where('email', 'like', 'generated.college-admin.%')->count())
            ->toBe($colleges->count() * 5);

        $departments->each(function (Department $department): void {
            expect(
                User::query()
                    ->where('email', 'like', 'generated.faculty.%')
                    ->whereHas('facultyProfile', fn ($query) => $query->where('department_id', $department->id))
                    ->count()
            )->toBe(10);

            expect(
                User::query()
                    ->where('email', 'like', 'generated.dept-admin.%')
                    ->whereHas('employeeProfile', fn ($query) => $query->where('department_id', $department->id))
                    ->count()
            )->toBe(5);
        });

        $colleges->each(function (College $college): void {
            expect(
                User::query()
                    ->where('email', 'like', 'generated.college-admin.%')
                    ->whereHas('employeeProfile', fn ($query) => $query->where('college_id', $college->id))
                    ->count()
            )->toBe(5);
        });

        expect(
            User::query()
                ->where('email', 'like', 'generated.college-admin.%')
                ->whereHas('roles', fn ($query) => $query->where('name', 'faculty'))
                ->count()
        )->toBe($colleges->count() * 3)
            ->and(
                User::query()
                    ->where('email', 'like', 'generated.college-admin.%')
                    ->whereHas('roles', fn ($query) => $query->where('name', 'deptAdmin'))
                    ->count()
            )->toBe($colleges->count() * 2)
            ->and(
                User::query()
                    ->where('email', 'like', 'generated.dept-admin.%')
                    ->whereHas('roles', fn ($query) => $query->where('name', 'faculty'))
                    ->count()
            )->toBe($departments->count() * 3)
            ->and(
                User::query()
                    ->where('email', 'like', 'generated.dept-admin.%')
                    ->whereHas('roles', fn ($query) => $query->where('name', 'collegeAdmin'))
                    ->count()
            )->toBe($departments->count() * 2);
    });
});
