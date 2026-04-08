<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Database\Factories\Concerns\ResolvesAcademicContext;
use Illuminate\Database\Eloquent\Factories\Factory;

class FacultyProfileFactory extends Factory
{
    use ResolvesAcademicContext;

    protected $model = FacultyProfile::class;

    public function definition(): array
    {
        $firstName = fake()->firstName();
        $middleName = fake()->boolean(40) ? fake()->lastName() : null;
        $lastName = fake()->lastName();
        $email = fake()->unique()->safeEmail();
        $department = $this->resolveDepartment();

        return array_merge($this->academicContext($department), [
            'user_id' => User::factory()->state([
                'name' => trim($firstName.' '.($middleName ? $middleName.' ' : '').$lastName),
                'email' => $email,
            ]),
            'employee_no' => 'FAC-'.fake()->unique()->numerify('#####'),
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'academic_rank' => fake()->randomElement(['Instructor I', 'Instructor II', 'Assistant Professor I']),
            'email' => fn (array $attributes) => User::query()->find($attributes['user_id'])?->email ?? $email,
            'contactno' => fake()->numerify('09#########'),
            'address' => fake()->address(),
            'sex' => fake()->randomElement(['Male', 'Female']),
            'birthday' => fake()->dateTimeBetween('-60 years', '-21 years')->format('Y-m-d'),
            'updated_by' => User::query()->inRandomOrder()->value('id'),
        ]);
    }

    public function forDepartment(Department $department): static
    {
        return $this->state(fn (): array => $this->academicContext($department));
    }
}
