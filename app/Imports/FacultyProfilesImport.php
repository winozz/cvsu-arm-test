<?php

namespace App\Imports;

use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class FacultyProfilesImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            if (empty($row['email'])) continue;

            $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));

            $user = User::firstOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $fullName,
                    'password' => Hash::make($row['password'] ?? 'password123'),
                ]
            );

            // Ensure they have the faculty role
            if (!$user->hasRole('faculty')) {
                $user->assignRole('faculty');
            }

            FacultyProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $row['first_name'] ?? '',
                    'middle_name' => $row['middle_name'] ?? '',
                    'last_name' => $row['last_name'] ?? '',
                    'email' => $row['email'],
                    'branch_id' => $row['branch_id'] ?? null,
                    'department_id' => $row['department_id'] ?? null,
                    'academic_rank' => $row['academic_rank'] ?? null,
                    'contactno' => $row['contactno'] ?? null,
                    'sex' => ucfirst(strtolower($row['sex'] ?? '')),
                    'birthday' => $row['birthday'] ?? null,
                    'address' => $row['address'] ?? null,
                ]
            );
        }
    }
}
