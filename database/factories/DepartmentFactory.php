<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => \App\Models\Branch::inRandomOrder()->first()->id ?? \App\Models\Branch::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('D-????')),
            'name' => $this->faker->words(3, true).' Department',
            'is_active' => true,
        ];
    }
}
