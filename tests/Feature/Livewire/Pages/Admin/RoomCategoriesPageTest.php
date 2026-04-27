<?php

use App\Livewire\Tables\Admin\RoomCategoriesTable;
use App\Models\Permission;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\User;
use Livewire\Livewire;

describe('admin room categories page', function () {
    beforeEach(function () {
        $this->abilities = [
            'room_categories.view',
            'room_categories.create',
            'room_categories.update',
            'room_categories.delete',
            'room_categories.restore',
        ];

        collect($this->abilities)->each(fn (string $ability) => Permission::findOrCreate($ability, 'web'));

        $this->user = actingUserWithPermissions($this->abilities);
    });

    it('creates and updates room categories including deactivation', function () {
        Livewire::actingAs($this->user)
            ->test('pages::admin.room-categories.index')
            ->call('openCreateModal')
            ->set('form.name', 'Seminar Room')
            ->set('form.slug', 'seminar-room')
            ->set('form.is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('roomCategoryModal', false)
            ->assertDispatched('pg:eventRefresh-roomCategoriesTable');

        $roomCategory = RoomCategory::query()->where('slug', 'seminar-room')->first();

        expect($roomCategory)->not->toBeNull()
            ->and($roomCategory->is_active)->toBeTrue();

        Livewire::actingAs($this->user)
            ->test('pages::admin.room-categories.index')
            ->call('openEditModal', $roomCategory->id)
            ->set('form.name', 'Seminar Suite')
            ->set('form.slug', 'seminar-suite')
            ->set('form.is_active', false)
            ->call('save')
            ->assertHasNoErrors();

        expect($roomCategory->fresh()->name)->toBe('Seminar Suite')
            ->and($roomCategory->fresh()->slug)->toBe('seminar-suite')
            ->and($roomCategory->fresh()->is_active)->toBeFalse();
    });

    it('blocks deleting in-use categories and allows delete restore for unused ones', function () {
        $categoryInUse = RoomCategory::factory()->create([
            'name' => 'Simulation Lab',
            'slug' => 'simulation-lab',
        ]);
        $unusedCategory = RoomCategory::factory()->create([
            'name' => 'Testing Room',
            'slug' => 'testing-room',
        ]);

        Room::factory()->create([
            'room_category_id' => $categoryInUse->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(RoomCategoriesTable::class)
            ->call('deleteRoomCategory', $categoryInUse->id);

        expect(RoomCategory::query()->find($categoryInUse->id)?->trashed())->toBeFalse();

        Livewire::actingAs($this->user)
            ->test(RoomCategoriesTable::class)
            ->call('deleteRoomCategory', $unusedCategory->id);

        expect(RoomCategory::withTrashed()->find($unusedCategory->id)?->trashed())->toBeTrue();

        Livewire::actingAs($this->user)
            ->test(RoomCategoriesTable::class)
            ->call('restoreRoomCategory', $unusedCategory->id);

        expect(RoomCategory::query()->find($unusedCategory->id)?->trashed())->toBeFalse();
    });

    it('allows only super admins with permission to access the room categories route', function () {
        $authorized = User::factory()->superAdmin()->create();
        $authorized->givePermissionTo('room_categories.view');

        $unauthorized = User::factory()->create();

        $this->actingAs($authorized)
            ->get(route('room-categories.index'))
            ->assertOk();

        $this->actingAs($unauthorized)
            ->get(route('room-categories.index'))
            ->assertRedirect(route('dashboard.resolve'));
    });
});
