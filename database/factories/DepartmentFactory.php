<?php

namespace Database\Factories;

use App\Models\College;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
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
        $college = College::query()->inRandomOrder()->first() ?? College::factory()->create();
        $departmentName = str(fake()->unique()->words(2, true))->title()->append(' Department')->toString();

        return [
            'campus_id' => $college->campus_id,
            'college_id' => $college->id,
            'name' => $departmentName,
            'code' => fake()->unique()->bothify('DEPT-###'),
            'description' => "Generated seed data for {$departmentName}.",
            'is_active' => true,
        ];
    }

    public function forCollege(College $college): static
    {
        return $this->state(fn (): array => [
            'campus_id' => $college->campus_id,
            'college_id' => $college->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
