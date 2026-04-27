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
            RoomCategorySeeder::class,
            RoomSeeder::class,
            SubjectSeeder::class,
            SubjectCategorySeeder::class,
            CurriculumSeeder::class,
            CurriculumEntrySeeder::class,
            SubjectProgramSeeder::class,
            PrerequisiteSeeder::class,
            PrerequisiteSubjectSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            GeneratedUserSeeder::class,
        ]);
    }
}
