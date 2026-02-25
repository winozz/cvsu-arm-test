<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['Main', 'Satellite']);

        return [
            'code' => strtoupper($this->faker->unique()->lexify('????')),
            'name' => 'CvSU '.$this->faker->randomElement(['Main', 'Satellite']).' - '.$this->faker->city,
            'type' => $this->faker->randomElement(['Main', 'Satellite']),
            'address' => $this->faker->address,
            'is_active' => true,
        ];
    }
}
