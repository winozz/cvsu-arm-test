<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Seed deterministic demo users that match the requested role structure.
     */
    public function run(): void
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->with(['campus', 'college'])
            ->orderBy('campus_id')
            ->orderBy('college_id')
            ->orderBy('name')
            ->get();

        if ($departments->isEmpty()) {
            throw new RuntimeException('Unable to seed users because no active departments are available.');
        }

        $ceitPrimaryDepartment = $this->findDepartmentForCollege('CEIT');
        $ceitSecondaryDepartment = $this->findDepartmentForCollege('CEIT', 1);
        $casPrimaryDepartment = $this->findDepartmentForCollege('CAS');

        $namedSuperAdmin = $this->upsertUser(
            'tristan.sangangbayan@cvsu.edu.ph',
            'Tristan Sangangbayan',
            ['superAdmin']
        );

        $this->deleteProfiles($namedSuperAdmin->id);

        $legacyCollegeAdmin = $this->upsertUser(
            'dlxks.sangangbayan@gmail.com',
            'College Admin',
            ['collegeAdmin']
        );

        $this->upsertEmployeeProfile($legacyCollegeAdmin, $ceitPrimaryDepartment, [
            'employee_no' => 'CADM-0001',
            'first_name' => 'College',
            'middle_name' => null,
            'last_name' => 'Admin',
            'position' => 'College Administrator',
        ]);

        $this->deleteFacultyProfile($legacyCollegeAdmin->id);

        $legacyDepartmentAdmin = $this->upsertUser(
            'sky.shira7@gmail.com',
            'Department Admin',
            ['deptAdmin']
        );

        $this->upsertEmployeeProfile($legacyDepartmentAdmin, $casPrimaryDepartment, [
            'employee_no' => 'DADM-0001',
            'first_name' => 'Department',
            'middle_name' => null,
            'last_name' => 'Admin',
            'position' => 'Department Administrator',
        ]);

        $this->deleteFacultyProfile($legacyDepartmentAdmin->id);

        $legacyFaculty = $this->upsertUser(
            'sangangbayant@gmail.com',
            'Faculty Account',
            ['faculty']
        );

        $this->upsertFacultyProfile($legacyFaculty, $ceitSecondaryDepartment, $namedSuperAdmin, [
            'employee_no' => 'FAC-0001',
            'first_name' => 'Faculty',
            'middle_name' => null,
            'last_name' => 'Account',
            'email' => $legacyFaculty->email,
            'academic_rank' => 'Instructor I',
            'contactno' => '09179876543',
            'address' => 'Indang, Cavite',
            'sex' => 'Male',
            'birthday' => '1994-10-12',
        ]);

        $this->deleteEmployeeProfile($legacyFaculty->id);

        $collegeAdminDepartments = $departments
            ->unique('college_id')
            ->reject(fn (Department $department): bool => $department->id === $ceitPrimaryDepartment->id)
            ->values();

        $deptAdminDepartments = $departments
            ->reject(fn (Department $department): bool => $department->id === $casPrimaryDepartment->id)
            ->values();

        $this->seedCollegeAdminBatch($collegeAdminDepartments, 5);
        $this->seedDeptAdminBatch($deptAdminDepartments, 5);
        $this->seedFacultyBatch($departments, 100, $namedSuperAdmin);
    }

    protected function upsertUser(string $email, string $name, array $roles): User
    {
        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $user->syncRoles($roles);

        return $user->fresh();
    }

    protected function upsertFacultyProfile(User $user, Department $department, User $updatedBy, array $attributes): void
    {
        $profile = FacultyProfile::withTrashed()->updateOrCreate(
            ['user_id' => $user->id],
            array_merge($this->academicContext($department), $attributes, [
                'email' => $attributes['email'] ?? $user->email,
                'updated_by' => $updatedBy->id,
            ])
        );

        if ($profile->trashed()) {
            $profile->restore();
        }
    }

    protected function upsertEmployeeProfile(User $user, Department $department, array $attributes): void
    {
        $profile = EmployeeProfile::withTrashed()->updateOrCreate(
            ['user_id' => $user->id],
            array_merge($this->academicContext($department), $attributes)
        );

        if ($profile->trashed()) {
            $profile->restore();
        }
    }

    protected function deleteProfiles(int $userId): void
    {
        $this->deleteFacultyProfile($userId);
        $this->deleteEmployeeProfile($userId);
    }

    protected function deleteFacultyProfile(int $userId): void
    {
        FacultyProfile::withTrashed()->where('user_id', $userId)->get()->each->delete();
    }

    protected function deleteEmployeeProfile(int $userId): void
    {
        EmployeeProfile::withTrashed()->where('user_id', $userId)->get()->each->delete();
    }

    protected function findDepartmentForCollege(string $collegeCode, int $offset = 0): Department
    {
        $department = Department::query()
            ->whereHas('college', fn ($query) => $query->where('code', $collegeCode))
            ->orderBy('id')
            ->skip($offset)
            ->first();

        if (! $department) {
            throw new RuntimeException("Unable to locate department offset [{$offset}] for college [{$collegeCode}].");
        }

        return $department;
    }

    protected function seedCollegeAdminBatch(Collection $departments, int $count): void
    {
        if ($departments->count() < 1) {
            throw new RuntimeException('Unable to seed college admins because no departments are available.');
        }

        foreach (range(1, $count) as $index) {
            /** @var Department $department */
            $department = $departments[($index - 1) % $departments->count()];
            $user = $this->upsertUser(
                sprintf('college.admin%02d@cvsu-arm.test', $index),
                sprintf('College Admin %02d', $index),
                ['collegeAdmin']
            );

            $this->upsertEmployeeProfile($user, $department, [
                'employee_no' => sprintf('CADM-%04d', $index + 1),
                'first_name' => 'College',
                'middle_name' => 'Admin',
                'last_name' => sprintf('%02d', $index),
                'position' => 'College Administrator',
            ]);

            $this->deleteFacultyProfile($user->id);
        }
    }

    protected function seedDeptAdminBatch(Collection $departments, int $count): void
    {
        if ($departments->count() < 1) {
            throw new RuntimeException('Unable to seed department admins because no departments are available.');
        }

        foreach (range(1, $count) as $index) {
            /** @var Department $department */
            $department = $departments[($index - 1) % $departments->count()];
            $user = $this->upsertUser(
                sprintf('dept.admin%02d@cvsu-arm.test', $index),
                sprintf('Department Admin %02d', $index),
                ['deptAdmin']
            );

            $this->upsertEmployeeProfile($user, $department, [
                'employee_no' => sprintf('DADM-%04d', $index + 1),
                'first_name' => 'Department',
                'middle_name' => 'Admin',
                'last_name' => sprintf('%02d', $index),
                'position' => 'Department Administrator',
            ]);

            $this->deleteFacultyProfile($user->id);
        }
    }

    protected function seedFacultyBatch(Collection $departments, int $count, User $updatedBy): void
    {
        if ($departments->count() < 1) {
            throw new RuntimeException('Unable to seed faculty users because no departments are available.');
        }

        foreach (range(1, $count) as $index) {
            /** @var Department $department */
            $department = $departments[($index - 1) % $departments->count()];
            $user = $this->upsertUser(
                sprintf('faculty.%03d@cvsu-arm.test', $index),
                sprintf('Faculty %03d', $index),
                ['faculty']
            );

            $this->upsertFacultyProfile($user, $department, $updatedBy, [
                'employee_no' => sprintf('FAC-%04d', $index + 1),
                'first_name' => 'Faculty',
                'middle_name' => 'Seed',
                'last_name' => sprintf('%03d', $index),
                'academic_rank' => 'Instructor I',
                'contactno' => sprintf('09%09d', $index),
                'address' => 'Indang, Cavite',
                'sex' => $index % 2 === 0 ? 'Female' : 'Male',
                'birthday' => now()->subYears(25 + ($index % 20))->toDateString(),
            ]);

            $this->deleteEmployeeProfile($user->id);
        }
    }

    /**
     * @return array{campus_id: int, college_id: int, department_id: int}
     */
    protected function academicContext(Department $department): array
    {
        return [
            'campus_id' => $department->campus_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ];
    }
}
