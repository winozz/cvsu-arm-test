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
    /**
     * Expected Excel Headers:
     * first_name | last_name | email | password | roles | type | branch_id | department_id
     * (roles can be comma-separated, e.g., "collegeAdmin,deptAdmin")
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['email'])) {
                continue;
            }

            // 1. Create or fetch the user
            $user = User::firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
                    'password' => Hash::make($row['password'] ?? 'password123'),
                ]
            );

            // 2. Assign multiple roles
            if (! empty($row['roles'])) {
                // Split by comma and trim whitespace
                $rolesArray = array_map('trim', explode(',', $row['roles']));
                $user->assignRole($rolesArray);
            }

            // 3. Create Profile Data
            $profileType = strtolower($row['type'] ?? '');

            if ($profileType === 'faculty') {
                FacultyProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $row['first_name'] ?? '',
                        'last_name' => $row['last_name'] ?? '',
                        'email' => $row['email'] ?? '',
                        'branch_id' => $row['branch_id'] ?? null,
                        'department_id' => $row['department_id'] ?? null,
                    ]
                );
            } elseif ($profileType === 'employee') {
                EmployeeProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $row['first_name'] ?? '',
                        'last_name' => $row['last_name'] ?? '',
                        'branch_id' => $row['branch_id'] ?? null,
                        'department_id' => $row['department_id'] ?? null,
                    ]
                );
            }
        }
    }
}
