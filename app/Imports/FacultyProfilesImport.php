<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FacultyProfilesImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        protected ?int $updatedBy = null,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            if (empty($row['email'])) {
                continue;
            }

            $assignment = $this->resolveAcademicAssignment($row, $index + 2);

            $fullName = trim(($row['first_name'] ?? '').' '.($row['middle_name'] ?? '').' '.($row['last_name'] ?? ''));

            $user = User::withTrashed()->firstOrNew(
                ['email' => $row['email']],
            );
            $user->fill([
                'name' => $fullName,
                'password' => Hash::make($row['password'] ?? 'password123'),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'is_active' => true,
            ]);
            $user->save();

            if ($user->trashed()) {
                $user->restore();
            }

            // Ensure they have the faculty role
            if (! $user->hasRole('faculty')) {
                $user->assignRole('faculty');
            }

            $facultyProfile = FacultyProfile::withTrashed()->updateOrCreate(
                ['user_id' => $user->id],
                array_merge($assignment, [
                    'first_name' => $row['first_name'] ?? '',
                    'middle_name' => $row['middle_name'] ?? '',
                    'last_name' => $row['last_name'] ?? '',
                    'email' => $row['email'],
                    'academic_rank' => $row['academic_rank'] ?? null,
                    'contactno' => $row['contactno'] ?? null,
                    'sex' => filled($row['sex'] ?? null) ? ucfirst(strtolower((string) $row['sex'])) : null,
                    'birthday' => $row['birthday'] ?? null,
                    'address' => $row['address'] ?? null,
                    'updated_by' => $this->updatedBy,
                ])
            );

            if ($facultyProfile->trashed()) {
                $facultyProfile->restore();
            }
        }
    }

    /**
     * @return array{campus_id: int, college_id: int, department_id: int}
     */
    protected function resolveAcademicAssignment(Collection $row, int $rowNumber): array
    {
        $departmentId = $row['department_id'] ?? null;
        $department = filled($departmentId)
            ? Department::query()->find((int) $departmentId)
            : null;

        if (! $department) {
            throw ValidationException::withMessages([
                'importFile' => "Row {$rowNumber} must include a valid department_id.",
            ]);
        }

        return [
            'campus_id' => (int) $department->campus_id,
            'college_id' => (int) $department->college_id,
            'department_id' => (int) $department->id,
        ];
    }
}
