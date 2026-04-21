<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\Role;
use App\Models\User;
use Database\Factories\Concerns\ResolvesAcademicContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    use ResolvesAcademicContext;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password123'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function faculty(): static
    {
        return $this->afterCreating(function (User $user): void {
            $department = $this->resolveDepartment();
            $nameParts = $this->nameParts($user);

            $this->ensureRoleExists('faculty');
            $user->syncRoles(['faculty']);

            FacultyProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($this->academicContext($department), [
                    'employee_no' => 'FAC-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                    'academic_rank' => 'Instructor I',
                    'email' => $user->email,
                ])
            );
        });
    }

    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $this->ensureRoleExists('superAdmin');
            $user->syncRoles(['superAdmin']);
        });
    }

    public function collegeAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $department = $this->resolveDepartment();
            $nameParts = $this->nameParts($user);

            $this->ensureRoleExists('collegeAdmin');
            $user->syncRoles(['collegeAdmin']);

            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($this->academicContext($department), [
                    'employee_no' => 'EMP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                    'position' => 'College Administrator',
                ])
            );
        });
    }

    public function deptAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $department = $this->resolveDepartment();
            $nameParts = $this->nameParts($user);

            $this->ensureRoleExists('deptAdmin');
            $user->syncRoles(['deptAdmin']);

            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($this->academicContext($department), [
                    'employee_no' => 'EMP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                    'position' => 'Department Administrator',
                ])
            );
        });
    }

    public function dualRole(): static
    {
        return $this->afterCreating(function (User $user): void {
            $department = $this->resolveDepartment();
            $nameParts = $this->nameParts($user);

            $this->ensureRoleExists('faculty');
            $this->ensureRoleExists('deptAdmin');
            $user->syncRoles(['faculty', 'deptAdmin']);

            FacultyProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($this->academicContext($department), [
                    'employee_no' => 'FAC-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                    'academic_rank' => 'Instructor I',
                    'email' => $user->email,
                ])
            );

            EmployeeProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($this->academicContext($department), [
                    'employee_no' => 'EMP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                    'first_name' => $nameParts['first_name'],
                    'middle_name' => $nameParts['middle_name'],
                    'last_name' => $nameParts['last_name'],
                    'position' => 'Department Administrator',
                ])
            );
        });
    }

    protected function ensureRoleExists(string $roleName): void
    {
        $role = Role::withTrashed()->firstOrNew([
            'name' => $roleName,
            'guard_name' => 'web',
        ]);

        $role->guard_name = 'web';
        $role->save();

        if ($role->trashed()) {
            $role->restore();
        }
    }

    /**
     * @return array{first_name: string, middle_name: string|null, last_name: string}
     */
    protected function nameParts(User $user): array
    {
        $parts = preg_split('/\s+/', trim($user->name)) ?: [];
        $firstName = $parts[0] ?? 'User';
        $lastName = count($parts) > 1 ? array_pop($parts) : 'Account';
        $middleName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
        ];
    }
}
