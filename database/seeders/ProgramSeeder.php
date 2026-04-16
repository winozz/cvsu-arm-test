<?php

namespace Database\Seeders;

use App\Models\Program;
use Database\Seeders\Support\CvsuSeedData;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CvsuSeedData::programs()->each(function (array $program): void {
            $record = Program::withTrashed()->updateOrCreate(
                [
                    'id' => $program['legacy_id'],
                ],
                [
                    'title' => $program['title'],
                    'code' => $program['code'],
                    'description' => $program['description'],
                    'no_of_years' => $program['no_of_years'],
                    'level' => $program['level'],
                    'is_active' => true,
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }
        });
    }
}
