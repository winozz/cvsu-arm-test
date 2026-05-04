<?php

namespace Database\Seeders;

use Database\Factories\RoomCategoryFactory;
use App\Models\RoomCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RoomCategorySeeder extends Seeder
{
    public function run(): void
    {
        collect(RoomCategoryFactory::DEFAULT_CATEGORY_NAMES)->each(function (string $name): void {
            $category = RoomCategory::withTrashed()->updateOrCreate(
                ['slug' => Str::slug($name)],
                RoomCategory::factory()->named($name)->make()->only(['name', 'slug', 'is_active'])
            );

            if ($category->trashed()) {
                $category->restore();
            }
        });
    }
}
