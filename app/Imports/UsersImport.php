<?php

namespace App\Imports;

use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['email'])) {
                continue;
            }

            // 1. Update or Create the main User
            $user = User::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                    'password' => Hash::make($row['password'] ?? 'password123'),
                ]
            );

            if (! empty($row['roles'])) {
                $user->syncRoles(array_map('trim', explode(',', $row['roles'])));
            }

            $type = strtolower($row['type'] ?? '');

            // 2. Sync Profile Data
            if ($type === 'faculty') {
                FacultyProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $row['first_name'] ?? '',
                        'middle_name' => $row['middle_name'] ?? '',
                        'last_name' => $row['last_name'] ?? '',
                        'email' => $user->email,
                        'branch_id' => $row['branch_id'] ?? null,
                        'department_id' => $row['department_id'] ?? null,
                        'academic_rank' => $row['academic_rank'] ?? null,
                    ]
                );
            } elseif ($type === 'employee') {
                EmployeeProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $row['first_name'] ?? '',
                        'middle_name' => $row['middle_name'] ?? '',
                        'last_name' => $row['last_name'] ?? '',
                        'branch_id' => $row['branch_id'] ?? null,
                        'department_id' => $row['department_id'] ?? null,
                        'position' => $row['position'] ?? null,
                    ]
                );
            }
        }
    }
}
