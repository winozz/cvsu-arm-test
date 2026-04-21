<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\College;
use Database\Seeders\Support\CvsuSeedData;
use Illuminate\Database\Seeder;
use RuntimeException;

class CollegeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $campusIds = Campus::query()->pluck('id');

        CvsuSeedData::colleges()->each(function (array $college) use ($campusIds): void {
            if (! $campusIds->contains($college['campus_legacy_id'])) {
                throw new RuntimeException("Unable to seed college [{$college['code']}] because campus id [{$college['campus_legacy_id']}] is missing.");
            }

            $record = College::withTrashed()->updateOrCreate(
                [
                    'id' => $college['legacy_id'],
                ],
                [
                    'campus_id' => $college['campus_legacy_id'],
                    'code' => $college['code'],
                    'name' => $college['name'],
                    'description' => $college['description'],
                    'is_active' => true,
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }
        });
    }
}
