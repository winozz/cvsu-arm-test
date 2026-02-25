<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mainBranch = Branch::where('type', 'Main')->first() ?? Branch::factory()->create();

        $departments = [
            ['code' => 'DIT', 'name' => 'Department of Information Technology'],
            ['code' => 'DCS', 'name' => 'Department of Computer Science'],
            ['code' => 'DBIO', 'name' => 'Department of Biology'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                array_merge($dept, ['branch_id' => $mainBranch->id])
            );
        }

        // Generate additional dummy departments
        Department::factory(10)->create();
    }
}
