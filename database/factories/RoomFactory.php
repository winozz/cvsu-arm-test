<?php

namespace Database\Factories;

use App\Enums\RoomStatusEnum;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomCategory;
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
        $roomCategory = RoomCategory::query()->inRandomOrder()->first() ?? RoomCategory::factory()->create([
            'name' => 'Lecture',
            'slug' => 'lecture',
        ]);
        $floorNo = (string) fake()->numberBetween(1, 5);
        $roomNo = (int) ((int) $floorNo * 100 + fake()->numberBetween(1, 30));

        return [
            'campus_id' => $department->campus_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
            'name' => sprintf('%s Room %d', $roomCategory->name, $roomNo),
            'floor_no' => $floorNo,
            'room_no' => $roomNo,
            'room_category_id' => $roomCategory->id,
            'description' => fake()->optional()->sentence(),
            'location' => sprintf('Building %d, Floor %s', $department->college_id, $floorNo),
            'is_active' => true,
            'status' => fake()->randomElement(array_map(
                static fn (RoomStatusEnum $status): string => strtoupper($status->value),
                RoomStatusEnum::cases()
            )),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function lecture(): static
    {
        return $this->state(fn (): array => [
            'room_category_id' => RoomCategory::query()->firstOrCreate(
                ['slug' => 'lecture'],
                ['name' => 'Lecture', 'is_active' => true]
            )->id,
        ]);
    }

    public function laboratory(): static
    {
        return $this->state(fn (): array => [
            'room_category_id' => RoomCategory::query()->firstOrCreate(
                ['slug' => 'laboratory'],
                ['name' => 'Laboratory', 'is_active' => true]
            )->id,
        ]);
    }
}
