<?php

namespace Database\Factories;

use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\User;
use Database\Factories\Concerns\ResolvesAcademicContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    use ResolvesAcademicContext;

    protected $model = EmployeeProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $middleName = fake()->boolean(40) ? fake()->lastName() : null;
        $lastName = fake()->lastName();
        $department = $this->resolveDepartment();

        return array_merge($this->academicContext($department), [
            'user_id' => User::factory()->state([
                'name' => trim($firstName.' '.($middleName ? $middleName.' ' : '').$lastName),
            ]),
            'employee_no' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'position' => fake()->jobTitle(),
        ]);
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (): array => $this->academicContext($department));
    }

    public function withoutDepartment(): static
    {
        return $this->state(function (): array {
            $college = College::query()->inRandomOrder()->first() ?? College::factory()->create();

            return [
                'campus_id' => $college->campus_id,
                'college_id' => $college->id,
                'department_id' => null,
            ];
        });
    }
}
