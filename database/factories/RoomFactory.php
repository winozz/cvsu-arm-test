<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $department = Department::query()->inRandomOrder()->first() ?? Department::factory()->create();
        $floorNo = (string) fake()->numberBetween(1, 5);
        $roomNo = (int) ((int) $floorNo * 100 + fake()->numberBetween(1, 30));
        $type = fake()->randomElement(['LECTURE', 'LABORATORY']);

        return [
            'campus_id' => $department->campus_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
            'name' => sprintf('%s Room %d', $type === 'LABORATORY' ? 'Laboratory' : 'Lecture', $roomNo),
            'floor_no' => $floorNo,
            'room_no' => $roomNo,
            'type' => $type,
            'description' => fake()->optional()->sentence(),
            'location' => sprintf('Building %d, Floor %s', $department->college_id, $floorNo),
            'is_active' => true,
            'status' => fake()->randomElement(['USEABLE', 'NOT_USEABLE', 'UNDER_RENOVATION', 'UNDER_CONSTRUCTION']),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function lecture(): static
    {
        return $this->state(fn (): array => ['type' => 'LECTURE']);
    }

    public function laboratory(): static
    {
        return $this->state(fn (): array => ['type' => 'LABORATORY']);
    }
}
