<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Department;
use Database\Seeders\Support\CvsuSeedData;
use Illuminate\Database\Seeder;
use RuntimeException;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $colleges = College::query()->get()->keyBy(
            fn (College $college): string => "{$college->campus_id}:{$college->code}"
        );

        CvsuSeedData::departments()->each(function (array $department) use ($colleges): void {
            /** @var College|null $college */
            $college = $colleges->get("{$department['campus_id']}:{$department['college_code']}");

            if (! $college) {
                throw new RuntimeException("Unable to seed department [{$department['code']}] because its college is missing.");
            }

            $record = Department::withTrashed()->updateOrCreate(
                [
                    'college_id' => $college->id,
                    'code' => $department['code'],
                ],
                [
                    'campus_id' => $college->campus_id,
                    'name' => $department['name'],
                    'description' => $department['description'],
                    'is_active' => true,
                ]
            );

            if ($record->trashed()) {
                $record->restore();
            }
        });
    }
}
