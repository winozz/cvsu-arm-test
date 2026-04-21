<?php

use App\Imports\RoomsImport;
use App\Livewire\Forms\Admin\RoomForm;
use App\Models\Department;
use App\Models\Room;
use App\Traits\CanManage;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions, WithFileUploads;

    public RoomForm $form;

    public bool $roomModal = false;

    public bool $importModal = false;

    public bool $isEditing = false;

    public $importFile;

    public int $campusId;

    public int $collegeId;

    public int $departmentId;

    public string $campusName = '';

    public string $collegeName = '';

    public string $departmentName = '';

    public function mount(): void
    {
        $this->ensureCanManage('rooms.view');

        $department = $this->currentDepartment();

        $this->campusId = (int) $department->campus_id;
        $this->collegeId = (int) $department->college_id;
        $this->departmentId = (int) $department->id;
        $this->campusName = $department->campus?->name ?? '-';
        $this->collegeName = $department->college?->name ?? '-';
        $this->departmentName = $department->name;

        $this->form->resetForm($this->campusId, $this->collegeId, $this->departmentId);
    }

    public function create(): void
    {
        $this->ensureCanManage('rooms.create');

        $this->resetValidation();
        $this->isEditing = false;
        $this->form->resetForm($this->campusId, $this->collegeId, $this->departmentId);
        $this->roomModal = true;
    }

    #[On('openEditRoomModal')]
    public function openEditRoomModal(Room $room): void
    {
        $this->ensureCanManage('rooms.update');

        abort_unless((int) $room->department_id === $this->departmentId, 404);

        $this->resetValidation();
        $this->isEditing = true;
        $this->form->setRoom($room);
        $this->roomModal = true;
    }

    public function closeRoomModal(): void
    {
        $this->roomModal = false;
        $this->isEditing = false;
        $this->resetValidation();
        $this->form->resetForm($this->campusId, $this->collegeId, $this->departmentId);
    }

    public function save(): void
    {
        $this->ensureCanManage($this->isEditing ? 'rooms.update' : 'rooms.create');

        $validated = $this->form->validateForm();

        if ($this->isEditing) {
            $this->form->room->update($this->form->payload($validated));
            $message = 'Room updated successfully.';
        } else {
            Room::create($this->form->payload($validated));
            $message = 'Room created successfully.';
        }

        $this->closeRoomModal();
        $this->toast()->success('Success', $message)->send();
        $this->dispatch('pg:eventRefresh-roomsTable');
    }

    public function import(): void
    {
        $this->ensureCanManage('rooms.create');

        $this->validate([
            'importFile' => ['required', 'mimes:csv,xlsx,xls'],
        ]);

        Excel::import(new RoomsImport($this->currentDepartment()), $this->importFile);

        $this->importModal = false;
        $this->importFile = null;
        $this->toast()->success('Imported', 'Rooms imported successfully.')->send();
        $this->dispatch('pg:eventRefresh-roomsTable');
    }

    protected function currentDepartment(): Department
    {
        $departmentId = auth()->user()?->employeeProfile?->department_id;

        abort_unless(filled($departmentId), 403);

        return Department::query()
            ->with(['campus', 'college'])
            ->findOrFail($departmentId);
    }
};
?>

<div>
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold dark:text-white">Rooms</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Managing rooms for {{ $departmentName }} under {{ $collegeName }}, {{ $campusName }}.
            </p>
        </div>

        <div class="flex gap-2">
            @can('rooms.create')
                <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray" text="Import Rooms" />
                <x-button wire:click="create" sm color="primary" icon="plus" text="New Room" />
            @endcan
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.rooms-table />
    </div>

    <x-modal wire="roomModal" title="{{ $isEditing ? 'Edit Room' : 'New Room' }}" size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Campus" :value="$campusName" disabled />
                <x-input label="College" :value="$collegeName" disabled />
                <x-input label="Department" :value="$departmentName" disabled />
                <x-input label="Room Name" wire:model="form.name" />
                <x-input label="Floor No." wire:model="form.floor_no" />
                <x-input label="Room No." type="number" wire:model="form.room_no" />

                <x-select.styled label="Type" wire:model="form.type" :options="collect(Room::TYPES)
                    ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
                    ->values()
                    ->toArray()"
                    select="label:label|value:value" />

                <x-select.styled label="Status" wire:model="form.status" :options="collect(Room::STATUSES)
                    ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
                    ->values()
                    ->toArray()"
                    select="label:label|value:value" />
            </div>

            <x-input label="Location" wire:model="form.location" />
            <x-textarea label="Description" wire:model="form.description" />

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="form.is_active" label="Room is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $form->is_active ? 'This room is available for active use.' : 'This room will be marked inactive.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            @canany(['rooms.create', 'rooms.update'])
                <x-button flat text="Cancel" wire:click="closeRoomModal" sm />
                <x-button color="primary" :text="$isEditing ? 'Save Changes' : 'Save Room'" wire:click="save" sm />
            @endcanany
        </x-slot:footer>
    </x-modal>

    <x-modal wire="importModal" title="Import Rooms">
        <div class="space-y-4">
            <x-upload wire:model="importFile" label="Select Excel/CSV File" hint="Supported files: .xlsx, .csv" />
            <p class="text-xs text-zinc-500">
                Imported rooms will be assigned to {{ $departmentName }} automatically.
                Recommended headers: name, floor_no, room_no, type, description, location, is_active, status
            </p>
        </div>

        <x-slot:footer>
            @can('rooms.create')
                <x-button flat text="Cancel" wire:click="$set('importModal', false)" sm />
                <x-button color="primary" text="Upload & Import" wire:click="import" wire:loading.attr="disabled" sm />
            @endcan
        </x-slot:footer>
    </x-modal>
</div>
