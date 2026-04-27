<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Livewire\Livewire;

describe('college and department room management', function () {
    beforeEach(function () {
        ensureRoles(['collegeAdmin', 'deptAdmin']);

        collect(['rooms.view', 'rooms.create', 'rooms.update'])
            ->each(fn (string $ability) => Permission::findOrCreate($ability, 'web'));

        $this->campus = Campus::factory()->create();
        $this->college = College::factory()->forCampus($this->campus)->create();
        $this->department = Department::factory()->forCollege($this->college)->create();
        $this->lectureCategory = RoomCategory::query()->where('slug', 'lecture')->firstOrFail();
        $this->auditoriumCategory = RoomCategory::query()->where('slug', 'auditorium')->firstOrFail();
    });

    it('allows college admins to create college-wide rooms without department floor and room number', function () {
        $user = User::factory()->collegeAdmin()->create();
        $user->givePermissionTo(['rooms.view', 'rooms.create', 'rooms.update']);
        $user->employeeProfile->update([
            'campus_id' => $this->campus->id,
            'college_id' => $this->college->id,
            'department_id' => $this->department->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test('pages::dept-admin.rooms.index')
            ->set('scope', 'college');

        $component
            ->call('create')
            ->set('form.name', 'Main Auditorium')
            ->set('form.department_id', null)
            ->set('form.floor_no', '')
            ->set('form.room_no', null)
            ->set('form.room_category_id', $this->auditoriumCategory->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('roomModal', false)
            ->assertDispatched('pg:eventRefresh-roomsTable');

        $room = Room::query()->where('name', 'Main Auditorium')->first();

        expect($room)->not->toBeNull()
            ->and($room->college_id)->toBe($this->college->id)
            ->and($room->department_id)->toBeNull()
            ->and($room->floor_no)->toBeNull()
            ->and($room->room_no)->toBeNull()
            ->and($room->room_category_id)->toBe($this->auditoriumCategory->id);
    });

    it('allows college admins to edit existing college-wide rooms without assigning a department', function () {
        $user = User::factory()->collegeAdmin()->create();
        $user->givePermissionTo(['rooms.view', 'rooms.create', 'rooms.update']);
        $user->employeeProfile->update([
            'campus_id' => $this->campus->id,
            'college_id' => $this->college->id,
            'department_id' => $this->department->id,
        ]);

        $room = Room::factory()->create([
            'campus_id' => $this->campus->id,
            'college_id' => $this->college->id,
            'department_id' => null,
            'name' => 'Main Auditorium',
            'floor_no' => null,
            'room_no' => null,
            'room_category_id' => $this->auditoriumCategory->id,
        ]);

        Livewire::actingAs($user)
            ->test('pages::dept-admin.rooms.index')
            ->set('scope', 'college')
            ->dispatch('openEditRoomModal', room: $room->id)
            ->set('form.name', 'Grand Auditorium')
            ->set('form.department_id', null)
            ->set('form.floor_no', '')
            ->set('form.room_no', null)
            ->set('form.room_category_id', $this->lectureCategory->id)
            ->call('save')
            ->assertHasNoErrors();

        expect($room->fresh()->name)->toBe('Grand Auditorium')
            ->and($room->fresh()->department_id)->toBeNull()
            ->and($room->fresh()->room_no)->toBeNull()
            ->and($room->fresh()->room_category_id)->toBe($this->lectureCategory->id);
    });

    it('keeps department ownership for dept admins even if the form is tampered with', function () {
        $user = User::factory()->deptAdmin()->create();
        $user->givePermissionTo(['rooms.view', 'rooms.create']);
        $user->employeeProfile->update([
            'campus_id' => $this->campus->id,
            'college_id' => $this->college->id,
            'department_id' => $this->department->id,
        ]);

        bindRequestToRoute(route('rooms.index'));

        Livewire::actingAs($user)
            ->test('pages::dept-admin.rooms.index')
            ->call('create')
            ->set('form.name', 'Dept Lecture Hall')
            ->set('form.department_id', null)
            ->set('form.floor_no', '')
            ->set('form.room_no', null)
            ->set('form.room_category_id', $this->lectureCategory->id)
            ->call('save')
            ->assertHasNoErrors();

        $room = Room::query()->where('name', 'Dept Lecture Hall')->first();

        expect($room)->not->toBeNull()
            ->and($room->department_id)->toBe($this->department->id)
            ->and($room->room_no)->toBeNull()
            ->and($room->floor_no)->toBeNull();
    });
});
