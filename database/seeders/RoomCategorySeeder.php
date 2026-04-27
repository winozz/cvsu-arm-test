<?php

namespace Database\Seeders;

use App\Models\RoomCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoomCategorySeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'Lecture',
            'Laboratory',
            'Lecture Laboratory',
            'Workshop',
            'Sports Facility',
            'Auditorium',
            'Office',
            'Conference Room',
        ])->each(function (string $name): void {
            $category = RoomCategory::withTrashed()->updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'is_active' => true,
                ]
            );

            if ($category->trashed()) {
                $category->restore();
            }
        });
    }
}
