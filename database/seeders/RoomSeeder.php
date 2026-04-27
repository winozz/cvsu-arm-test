<?php

namespace Database\Seeders;

use App\Enums\RoomStatusEnum;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            ['floor_no' => '1', 'room_no' => 101, 'slug' => 'lecture', 'status' => strtoupper(RoomStatusEnum::USEABLE->value)],
            ['floor_no' => '1', 'room_no' => 102, 'slug' => 'lecture', 'status' => strtoupper(RoomStatusEnum::USEABLE->value)],
            ['floor_no' => '2', 'room_no' => 201, 'slug' => 'lecture', 'status' => strtoupper(RoomStatusEnum::USEABLE->value)],
            ['floor_no' => '2', 'room_no' => 210, 'slug' => 'laboratory', 'status' => strtoupper(RoomStatusEnum::USEABLE->value)],
        ];

        Department::query()->get(['id', 'campus_id', 'college_id', 'code'])->each(function (Department $department) use ($templates): void {
            foreach ($templates as $template) {
                $generatedRoomNo = (int) ($department->id * 1000 + $template['room_no']);
                $category = RoomCategory::query()->where('slug', $template['slug'])->firstOrFail();
                $baseName = $category->name === 'Laboratory' ? 'Laboratory' : 'Lecture Room';

                $room = Room::withTrashed()->updateOrCreate(
                    [
                        'department_id' => $department->id,
                        'room_no' => $generatedRoomNo,
                    ],
                    [
                        'campus_id' => $department->campus_id,
                        'college_id' => $department->college_id,
                        'name' => sprintf('%s %s-%d', $baseName, $department->code, $template['room_no']),
                        'floor_no' => $template['floor_no'],
                        'room_category_id' => $category->id,
                        'description' => sprintf('Auto-generated %s for %s.', strtolower($baseName), $department->code),
                        'location' => sprintf('College %d, Floor %s', $department->college_id, $template['floor_no']),
                        'is_active' => true,
                        'status' => $template['status'],
                    ]
                );

                if ($room->trashed()) {
                    $room->restore();
                }
            }
        });
    }
}
