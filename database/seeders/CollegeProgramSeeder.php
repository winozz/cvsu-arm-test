<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Program;
use Database\Seeders\Support\CvsuSeedData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CollegeProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $collegeIds = College::query()->pluck('id');
        $programIds = Program::query()->pluck('id');
        $timestamp = now();

        $records = CvsuSeedData::collegePrograms()->map(function (array $assignment) use ($collegeIds, $programIds, $timestamp): array {
            if (! $collegeIds->contains($assignment['college_legacy_id'])) {
                throw new RuntimeException("Unable to seed college program assignment [{$assignment['legacy_id']}] because college id [{$assignment['college_legacy_id']}] is missing.");
            }

            if (! $programIds->contains($assignment['program_legacy_id'])) {
                throw new RuntimeException("Unable to seed college program assignment [{$assignment['legacy_id']}] because program id [{$assignment['program_legacy_id']}] is missing.");
            }

            return [
                'college_id' => $assignment['college_legacy_id'],
                'program_id' => $assignment['program_legacy_id'],
                'created_at' => $assignment['created_at'] ?? $timestamp,
                'updated_at' => $assignment['updated_at'] ?? $timestamp,
            ];
        })->all();

        DB::table('college_programs')->upsert(
            $records,
            ['college_id', 'program_id'],
            ['updated_at']
        );
    }
}
