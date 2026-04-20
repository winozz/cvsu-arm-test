<?php

use Database\Seeders\CampusSeeder;
use Database\Seeders\CollegeProgramSeeder;
use Database\Seeders\CollegeSeeder;
use Database\Seeders\ProgramSeeder;
use Database\Seeders\Support\CvsuSeedData;
use Illuminate\Support\Facades\DB;

it('seeds college_programs from the csv source data', function () {
    $this->seed([
        CampusSeeder::class,
        CollegeSeeder::class,
        ProgramSeeder::class,
        CollegeProgramSeeder::class,
    ]);

    $expectedAssignments = CvsuSeedData::collegePrograms();

    expect(DB::table('college_programs')->count())->toBe($expectedAssignments->count())
        ->and(DB::table('college_programs')->where([
            'college_id' => 5,
            'program_id' => 401,
        ])->exists())->toBeTrue()
        ->and(DB::table('college_programs')->where([
            'college_id' => 4,
            'program_id' => 399,
        ])->exists())->toBeTrue();
});
