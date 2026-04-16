<?php

namespace Database\Factories;

use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Bachelor of Science in '.fake()->unique()->words(3, true),
            'code' => fake()->unique()->bothify('BS???'),
            'description' => null,
            'no_of_years' => fake()->randomElement([2, 4, 5, 6]),
            'level' => fake()->randomElement(['UNDERGRADUATE', 'GRADUATE', 'PRE-BACCALAUREATE', 'POST-BACCALAUREATE']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
