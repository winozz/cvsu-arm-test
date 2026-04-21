<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\College;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<College>
 */
class CollegeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'College of '.fake()->unique()->words(3, true);

        return [
            'campus_id' => Campus::query()->inRandomOrder()->value('id') ?? Campus::factory(),
            'name' => str($name)->title()->toString(),
            'code' => fake()->unique()->bothify('???'),
            'description' => "Generated seed data for {$name}.",
            'is_active' => true,
        ];
    }

    public function forCampus(Campus $campus): static
    {
        return $this->state(fn (): array => ['campus_id' => $campus->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
