<?php

use App\Livewire\Forms\Admin\RoomCategoryForm;
use App\Models\RoomCategory;
use App\Traits\CanManage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public RoomCategoryForm $form;

    public bool $roomCategoryModal = false;

    public bool $isEditing = false;

    public function mount(): void
    {
        $this->ensureCanManage('room_categories.view');
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => RoomCategory::query()->count(),
            'active' => RoomCategory::query()->where('is_active', true)->count(),
            'inactive' => RoomCategory::query()->where('is_active', false)->count(),
        ];
    }

    public function openCreateModal(): void
    {
        $this->ensureCanManage('room_categories.create');

        $this->resetValidation();
        $this->form->resetForm();
        $this->isEditing = false;
        $this->roomCategoryModal = true;
    }

    #[On('editRoomCategory')]
    public function openEditModal(RoomCategory $roomCategory): void
    {
        $this->ensureCanManage('room_categories.update');

        $this->resetValidation();
        $this->form->setRoomCategory($roomCategory);
        $this->isEditing = true;
        $this->roomCategoryModal = true;
    }

    public function save(): void
    {
        $this->ensureCanManage($this->isEditing ? 'room_categories.update' : 'room_categories.create');

        try {
            $validated = $this->form->validateForm();

            if ($this->isEditing) {
                $this->form->roomCategory->update($validated);
                $message = 'Room category updated successfully.';
            } else {
                RoomCategory::create($validated);
                $message = 'Room category created successfully.';
            }

            $this->roomCategoryModal = false;
            $this->dispatch('pg:eventRefresh-roomCategoriesTable');
            $this->toast()->success('Success', $message)->send();
        } catch (ValidationException $exception) {
            throw $exception;
        }
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Room Category Management</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Manage the catalog of room categories available to room records across colleges and departments.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Categories</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
                </div>

                <div class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-icon icon="squares-2x2" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->stats['active'] }}</p>
                </div>

                <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <x-icon icon="check-circle" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Inactive</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600">{{ $this->stats['inactive'] }}</p>
                </div>

                <div class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300">
                    <x-icon icon="pause-circle" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
    </div>

    <x-card>
        <div class="flex flex-col gap-4 border-b border-zinc-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold dark:text-white">Room Category List</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Keep room category names, slugs, and availability aligned with the scheduling catalog.
                </p>
            </div>

            @can('room_categories.create')
                <x-button wire:click="openCreateModal" sm color="primary" icon="plus" text="New Room Category" />
            @endcan
        </div>

        <div class="p-6">
            <livewire:tables.admin.room-categories-table />
        </div>
    </x-card>

    <x-modal wire="roomCategoryModal" title="{{ $isEditing ? 'Edit Room Category' : 'New Room Category' }}">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Name" wire:model="form.name" />
                <x-input label="Slug" wire:model="form.slug" hint="Use a stable lowercase slug, e.g. lecture-laboratory" />
            </div>

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="form.is_active" label="Category is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $form->is_active ? 'This category can be assigned to rooms.' : 'This category will be hidden from new room assignments.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            @canany(['room_categories.create', 'room_categories.update'])
                <x-button flat text="Cancel" wire:click="$set('roomCategoryModal', false)" sm />
                <x-button color="primary" :text="$isEditing ? 'Save Changes' : 'Save Room Category'" wire:click="save" sm />
            @endcanany
        </x-slot:footer>
    </x-modal>
</div>
