<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

describe('rooms migration schema', function () {
    it('creates rooms table with room category support in a fresh migration', function () {
        Schema::dropAllTables();

        foreach ([
            '2026_04_08_032522_create_campuses_table.php',
            '2026_04_08_033311_create_colleges_table.php',
            '2026_04_08_033637_create_departments_table.php',
            '2026_04_27_000001_create_room_categories_table.php',
            '2026_04_15_013153_create_rooms_table.php',
        ] as $migrationFile) {
            (require database_path('migrations/'.$migrationFile))->up();
        }

        expect(Schema::hasColumn('rooms', 'room_category_id'))->toBeTrue()
            ->and(Schema::hasColumn('rooms', 'type'))->toBeFalse();

        DB::table('campuses')->insert([
            'id' => 1,
            'name' => 'Main Campus',
            'code' => 'MC',
            'description' => 'Test campus',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('colleges')->insert([
            'id' => 1,
            'campus_id' => 1,
            'name' => 'College of Engineering',
            'code' => 'COE',
            'description' => 'Test college',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('departments')->insert([
            'id' => 1,
            'campus_id' => 1,
            'college_id' => 1,
            'name' => 'Mechanical Engineering',
            'code' => 'ME',
            'description' => 'Test department',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lectureCategoryId = DB::table('room_categories')->where('slug', 'lecture')->value('id');

        DB::table('rooms')->insert([
            [
                'id' => 1,
                'campus_id' => 1,
                'college_id' => 1,
                'department_id' => 1,
                'name' => 'Legacy Lecture Room',
                'floor_no' => '1',
                'room_no' => 101,
                'room_category_id' => $lectureCategoryId,
                'description' => null,
                'location' => null,
                'is_active' => true,
                'status' => 'USEABLE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        expect(DB::table('rooms')->where('id', 1)->value('room_category_id'))->toBe($lectureCategoryId)
            ->and(Schema::hasColumn('rooms', 'type'))->toBeFalse();
    });
});
