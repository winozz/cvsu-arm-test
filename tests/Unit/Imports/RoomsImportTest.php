<?php

use App\Imports\RoomsImport;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('RoomsImport', function () {
    it('accepts room category names and slugs while allowing blank floor and room numbers', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create();

        $rows = new Collection([
            collect([
                'name' => 'Flexible Lecture Space',
                'room_category' => 'Lecture',
                'floor_no' => '',
                'room_no' => '',
                'status' => 'Useable',
            ]),
            collect([
                'name' => 'Conference Annex',
                'room_category' => 'conference-room',
                'floor_no' => '',
                'room_no' => '',
                'status' => 'Under Renovation',
            ]),
        ]);

        (new RoomsImport($department))->collection($rows);

        $lectureRoom = Room::query()->where('name', 'Flexible Lecture Space')->first();
        $conferenceRoom = Room::query()->where('name', 'Conference Annex')->first();

        expect($lectureRoom)->not->toBeNull()
            ->and($lectureRoom->floor_no)->toBeNull()
            ->and($lectureRoom->room_no)->toBeNull()
            ->and($lectureRoom->roomCategory?->slug)->toBe('lecture')
            ->and($conferenceRoom)->not->toBeNull()
            ->and($conferenceRoom->roomCategory?->slug)->toBe('conference-room')
            ->and($conferenceRoom->status)->toBe('UNDER_RENOVATION');
    });

    it('defaults missing category values to lecture', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create();

        $rows = new Collection([
            collect([
                'name' => 'Default Category Room',
                'floor_no' => '',
                'room_no' => '',
                'status' => 'Useable',
            ]),
        ]);

        (new RoomsImport($department))->collection($rows);

        $room = Room::query()->where('name', 'Default Category Room')->first();

        expect($room)->not->toBeNull()
            ->and($room->roomCategory?->slug)->toBe('lecture');
    });
});
