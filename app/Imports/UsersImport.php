<?php

namespace App\Imports;

use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
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
            if (empty($row['email'])) {
                continue;
            }

            $user = User::withTrashed()->firstOrNew(['email' => $row['email']]);
            $user->fill([
                'name' => trim(($row['first_name'] ?? '').' '.($row['middle_name'] ?? '').' '.($row['last_name'] ?? '')),
                'password' => Hash::make($row['password'] ?? 'password123'),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'is_active' => true,
            ]);
            $user->save();

            if ($user->trashed()) {
                $user->restore();
            }

            if (! empty($row['roles'])) {
                $user->syncRoles(array_map('trim', explode(',', $row['roles'])));
            }

            $type = strtolower((string) ($row['type'] ?? ''));
            $assignment = $this->resolveAcademicAssignment($row);
            $profileNames = [
                'first_name' => $row['first_name'] ?? '',
                'middle_name' => $row['middle_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
            ];

            if ($type === 'faculty') {
                $facultyProfile = FacultyProfile::withTrashed()->updateOrCreate(
                    ['user_id' => $user->id],
                    array_merge($profileNames, $assignment, [
                        'email' => $user->email,
                        'academic_rank' => $row['academic_rank'] ?? null,
                        'contactno' => $row['contactno'] ?? null,
                        'sex' => filled($row['sex'] ?? null) ? ucfirst(strtolower((string) $row['sex'])) : null,
                        'birthday' => $row['birthday'] ?? null,
                        'address' => $row['address'] ?? null,
                    ])
                );

                if ($facultyProfile->trashed()) {
                    $facultyProfile->restore();
                }

                EmployeeProfile::withTrashed()->where('user_id', $user->id)->delete();
            } elseif ($type === 'employee') {
                $employeeProfile = EmployeeProfile::withTrashed()->updateOrCreate(
                    ['user_id' => $user->id],
                    array_merge($profileNames, $assignment, [
                        'position' => $row['position'] ?? null,
                    ])
                );

                if ($employeeProfile->trashed()) {
                    $employeeProfile->restore();
                }

                FacultyProfile::withTrashed()->where('user_id', $user->id)->delete();
            } else {
                FacultyProfile::withTrashed()->where('user_id', $user->id)->delete();
                EmployeeProfile::withTrashed()->where('user_id', $user->id)->delete();
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
}
