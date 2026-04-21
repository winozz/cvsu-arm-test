<?php

namespace Database\Seeders;

use App\Models\Campus;
use Database\Seeders\Support\CvsuSeedData;
use Illuminate\Database\Seeder;

class CampusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CvsuSeedData::campuses()->each(function (array $campus): void {
            $record = Campus::withTrashed()->updateOrCreate(
                ['id' => $campus['legacy_id']],
                [
                    'code' => $campus['code'],
                    'name' => $campus['name'],
                    'description' => $campus['description'],
                    'is_active' => true,
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }
        });
    }
}
