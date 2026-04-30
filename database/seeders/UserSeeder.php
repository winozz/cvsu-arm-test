<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class UserSeeder extends Seeder
{
    /**
     * Seed the legacy application accounts.
     */
    public function run(): void
    {
        $ceitPrimaryDepartment = $this->findDepartmentForCollege('CEIT');
        $ceitSecondaryDepartment = $this->findDepartmentForCollege('CEIT', 1);
        $casPrimaryDepartment = $this->findDepartmentForCollege('CAS');
        $cspearPrimaryDepartment = $this->findDepartmentForCollege('CSPEAR');

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

        $legacyCspearCollegeAdmin = $this->upsertUser(
            'ljohnmark9@gmail.com',
            'CSPEAR College Admin',
            ['collegeAdmin']
        );

        $this->upsertEmployeeProfile($legacyCspearCollegeAdmin, $cspearPrimaryDepartment, [
            'employee_no' => 'CADM-0002',
            'first_name' => 'CSPEAR',
            'middle_name' => null,
            'last_name' => 'Admin',
            'position' => 'College Administrator',
        ]);

        $this->deleteFacultyProfile($legacyCspearCollegeAdmin->id);

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
