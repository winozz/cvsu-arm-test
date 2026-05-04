<?php

namespace Database\Factories;

use App\Models\RoomCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomCategory>
 */
class RoomCategoryFactory extends Factory
{
    public const DEFAULT_CATEGORY_NAMES = [
        'Lecture',
        'Laboratory',
        'Lecture Laboratory',
        'Workshop',
        'Sports Facility',
        'Auditorium',
        'Office',
        'Conference Room',
    ];

    protected $model = RoomCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function named(string $name): static
    {
        return $this->state(fn (): array => [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }
}
