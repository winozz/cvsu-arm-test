<?php

namespace App\Imports;

use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));

            if ($email === '') {
                continue;
            }

            $user = User::withTrashed()->firstOrNew(['email' => $email]);
            $user->fill([
                'name' => $this->fullNameFromRow($row),
                'password' => filled($row['password'] ?? null)
                    ? Hash::make((string) $row['password'])
                    : ($user->password ?: Hash::make('password123')),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'is_active' => $this->normalizeBoolean($row['is_active'] ?? true),
            ]);
            $user->save();

            if ($user->trashed()) {
                $user->restore();
            }

            if ($row->has('roles')) {
                $user->syncRoles($this->csvValues($row['roles']));
            }

            if ($row->has('direct_permissions')) {
                $user->syncPermissions(
                    Permission::query()->whereIn('name', $this->csvValues($row['direct_permissions']))->get()
                );
            }

            $type = strtolower(trim((string) ($row['type'] ?? 'standard')));
            $assignment = $this->resolveAcademicAssignment($row);
            $profileNames = [
                'first_name' => $row['first_name'] ?? '',
                'middle_name' => $row['middle_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
            ];

            if (in_array($type, ['faculty', 'dual'], true)) {
                $this->syncFacultyProfile($user, $profileNames, $assignment, $row);
            } else {
                FacultyProfile::query()->where('user_id', $user->id)->delete();
            }

            if (in_array($type, ['employee', 'dual'], true)) {
                $this->syncEmployeeProfile($user, $profileNames, $assignment, $row);
            } else {
                EmployeeProfile::query()->where('user_id', $user->id)->delete();
            }
        }
    }

    /**
     * @return array{campus_id: int|null, college_id: int|null, department_id: int|null}
     */
    protected function resolveAcademicAssignment(Collection $row): array
    {
        $departmentId = $row['department_id'] ?? null;
        $collegeId = $row['college_id'] ?? null;
        $campusId = $row['campus_id'] ?? $row['branch_id'] ?? null;

        if (filled($departmentId)) {
            $department = Department::query()->find($departmentId);

            if ($department) {
                return [
                    'campus_id' => $department->campus_id,
                    'college_id' => $department->college_id,
                    'department_id' => $department->id,
                ];
            }
        }

        if (filled($collegeId)) {
            $college = College::query()->find($collegeId);

            if ($college) {
                return [
                    'campus_id' => $college->campus_id,
                    'college_id' => $college->id,
                    'department_id' => null,
                ];
            }
        }

        return [
            'campus_id' => filled($campusId) ? (int) $campusId : null,
            'college_id' => null,
            'department_id' => null,
        ];
    }

    protected function syncFacultyProfile(User $user, array $profileNames, array $assignment, Collection $row): void
    {
        $profile = FacultyProfile::withTrashed()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first() ?? new FacultyProfile(['user_id' => $user->id]);

        $profile->fill(array_merge($profileNames, $assignment, [
            'email' => $user->email,
            'academic_rank' => $this->blankToNull($row['academic_rank'] ?? null),
            'contactno' => $this->blankToNull($row['contactno'] ?? null),
            'sex' => filled($row['sex'] ?? null) ? ucfirst(strtolower((string) $row['sex'])) : null,
            'birthday' => $this->blankToNull($row['birthday'] ?? null),
            'address' => $this->blankToNull($row['address'] ?? null),
        ]));
        $profile->user_id = $user->id;
        $profile->save();

        if ($profile->trashed()) {
            $profile->restore();
        }
    }

    protected function syncEmployeeProfile(User $user, array $profileNames, array $assignment, Collection $row): void
    {
        $profile = EmployeeProfile::withTrashed()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first() ?? new EmployeeProfile(['user_id' => $user->id]);

        $profile->fill(array_merge($profileNames, $assignment, [
            'position' => $this->blankToNull($row['position'] ?? null),
        ]));
        $profile->user_id = $user->id;
        $profile->save();

        if ($profile->trashed()) {
            $profile->restore();
        }
    }

    protected function fullNameFromRow(Collection $row): string
    {
        return trim(implode(' ', array_filter([
            trim((string) ($row['first_name'] ?? '')),
            trim((string) ($row['middle_name'] ?? '')),
            trim((string) ($row['last_name'] ?? '')),
        ])));
    }

    protected function csvValues(mixed $value): array
    {
        if (! filled($value)) {
            return [];
        }

        return collect(explode(',', (string) $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    protected function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'active'], true);
    }
}
