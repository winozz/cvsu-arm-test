<?php

use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\Room;
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
            'type',
            'description',
            'location',
            'is_active',
            'status',
        ]);
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
