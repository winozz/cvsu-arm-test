<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CampusSeeder::class,
            CollegeSeeder::class,
            DepartmentSeeder::class,
            PermissionSeeder::class,
            ProgramSeeder::class,
            CollegeProgramSeeder::class,
            RoomSeeder::class,
            SubjectSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
        ]);
    }
}
