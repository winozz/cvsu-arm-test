<?php

use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Program model', function () {
    beforeEach(function () {
        $this->program = Program::factory()->make();
    });

    it('defines the expected fillable attributes', function () {
        expect($this->program->getFillable())->toBe([
            'code',
            'title',
            'description',
            'no_of_years',
            'level',
            'is_active',
        ]);
    });

    it('exposes human readable level and duration labels', function () {
        $program = Program::factory()->make([
            'level' => 'UNDERGRADUATE',
            'no_of_years' => 4,
        ]);

        expect($program->level_label)->toBe('Undergraduate')
            ->and($program->duration_label)->toBe('4 years');
    });
});

describe('Room model', function () {
    beforeEach(function () {
        $this->room = Room::factory()->make();
    });

    it('defines the expected fillable attributes', function () {
        expect($this->room->getFillable())->toBe([
            'campus_id',
            'college_id',
            'department_id',
            'name',
            'floor_no',
            'room_no',
            'room_category_id',
            'description',
            'location',
            'is_active',
            'status',
        ]);
    });

    it('resolves category labels and sensible display names when the room number is optional', function () {
        $category = RoomCategory::query()->firstOrCreate(
            ['slug' => 'auditorium'],
            ['name' => 'Auditorium', 'is_active' => true]
        );

        $room = Room::factory()->make([
            'name' => 'Main Auditorium',
            'room_no' => null,
            'room_category_id' => $category->id,
        ]);
        $room->setRelation('roomCategory', $category);

        expect($room->type_label)->toBe('Auditorium')
            ->and($room->display_name)->toBe('Main Auditorium');
    });
});

describe('RoomCategory model', function () {
    beforeEach(function () {
        $this->roomCategory = new RoomCategory();
    });

    it('defines the expected fillable attributes', function () {
        expect($this->roomCategory->getFillable())->toBe(['name', 'slug', 'is_active']);
    });
});

describe('Subject model', function () {
    beforeEach(function () {
        $this->subject = Subject::factory()->make();
    });

    it('defines the expected fillable attributes', function () {
        expect($this->subject->getFillable())->toBe([
            'code',
            'title',
            'description',
            'lecture_units',
            'laboratory_units',
            'is_credit',
            'is_active',
        ]);
    });
});

describe('Role model', function () {
    beforeEach(function () {
        $this->role = new Role();
    });

    it('defines the expected fillable attributes', function () {
        expect($this->role->getFillable())->toBe(['name', 'guard_name']);
    });
});

describe('Permission model', function () {
    beforeEach(function () {
        $this->permission = new Permission();
    });

    it('defines the expected fillable attributes', function () {
        expect($this->permission->getFillable())->toBe(['name', 'guard_name']);
    });
});
