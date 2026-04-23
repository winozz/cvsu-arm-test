<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('???###')),
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'lecture_units' => fake()->numberBetween(1, 3),
            'laboratory_units' => fake()->numberBetween(0, 3),
            'is_credit' => fake()->boolean(90),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
