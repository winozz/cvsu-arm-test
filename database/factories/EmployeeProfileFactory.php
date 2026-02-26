<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeProfileFactory extends Factory
{
    protected $model = EmployeeProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->lastName(),
            'last_name' => fake()->lastName(),
            'position' => fake()->jobTitle(),
            'branch_id' => \App\Models\Branch::inRandomOrder()->first()->id ?? \App\Models\Branch::factory(),
            'department_id' => \App\Models\Department::inRandomOrder()->first()->id ?? \App\Models\Department::factory(),
        ];
    }
}
