<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['superAdmin', 'faculty', 'collegeAdmin', 'deptAdmin'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $branch = Branch::first();
        $department = Department::first();

        // 1. SUPER ADMIN (User Only)
        $superAdmin = User::updateOrCreate(
            ['email' => 'tristan.sangangbayan@cvsu.edu.ph'],
            ['name' => 'Tristan Sangangbayan', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $superAdmin->assignRole('superAdmin');

        // 2. COLLEGE ADMIN (Employee Only)
        $collegeAdmin = User::updateOrCreate(
            ['email' => 'dlxks.sangangbayan@gmail.com'],
            ['name' => 'College Admin', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $collegeAdmin->assignRole('collegeAdmin');
        $collegeAdmin->employeeProfile()->updateOrCreate(['user_id' => $collegeAdmin->id], [
            'first_name' => 'College', 'last_name' => 'Admin', 'branch_id' => $branch->id, 'department_id' => $department->id,
        ]);

        // 3. DEPARTMENT ADMIN (Employee Only)
        $deptAdmin = User::updateOrCreate(
            ['email' => 'sky.shira7@gmail.com'],
            ['name' => 'Department Admin', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $deptAdmin->assignRole('deptAdmin');
        $deptAdmin->employeeProfile()->updateOrCreate(['user_id' => $deptAdmin->id], [
            'first_name' => 'Department', 'last_name' => 'Admin', 'branch_id' => $branch->id, 'department_id' => $department->id,
        ]);

        // 4. FACULTY (Faculty Only)
        $faculty = User::updateOrCreate(
            ['email' => 'sangangbayant@gmail.com'],
            ['name' => 'Faculty Account', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );
        $faculty->assignRole(['faculty', 'collegeAdmin']);
        $faculty->facultyProfile()->updateOrCreate(['user_id' => $faculty->id], [
            'first_name' => 'Faculty', 'last_name' => 'Account', 'email' => 'sangangbayant@gmail.com',
            'branch_id' => $branch->id, 'department_id' => $department->id,
        ]);

        // Dummy Data Generation
        User::factory(100)->faculty()->create(); // 10 Faculty Only
        User::factory(5)->collegeAdmin()->create(); // 5 Employees Only
        User::factory(5)->deptAdmin()->create(); // 5 Department Admins Only
        User::factory(5)->dualRole()->create(); // 5 Faculty + DeptAdmin
    }
}
