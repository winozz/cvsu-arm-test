<?php

namespace Database\Seeders;

use App\Models\Subject;
use Database\Seeders\Support\CvsuSeedData;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CvsuSeedData::subjects()->each(function (array $subject): void {
            $record = Subject::withTrashed()->updateOrCreate(
                [
                    'id' => $subject['legacy_id'],
                ],
                [
                    'title' => $subject['title'],
                    'code' => $subject['code'],
                    'description' => $subject['description'],
                    'lecture_units' => $subject['lecture_units'],
                    'laboratory_units' => $subject['laboratory_units'],
                    'is_credit' => $subject['is_credit'],
                    'is_active' => true,
                    'status' => Subject::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }
        });
    }
}
