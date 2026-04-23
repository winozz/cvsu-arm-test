<?php

use App\Enums\RoomStatusEnum;
use App\Imports\RoomsImport;
use App\Livewire\Forms\Admin\RoomForm;
use App\Models\College;
use App\Models\Department;
use App\Models\Room;
use App\Traits\CanManage;
use Livewire\Attributes\Computed;
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

    public string $scope = 'department';

    public string $campusName = '';

    public string $collegeCode = '';

    public string $collegeName = '';

    public string $departmentCode = '';

    public string $departmentName = '';

    public array $departmentOptions = [];

    public function mount(): void
    {
        $this->ensureCanManage('rooms.view');

        $this->scope = request()->routeIs('college-rooms.*') ? 'college' : 'department';

        $department = $this->currentDepartment();

        $this->syncContextFromDepartment($department);

        if ($this->scope === 'college') {
            $college = $this->currentCollege();
            $this->collegeId = (int) $college->id;
            $this->collegeCode = $college->code ?? '-';
            $this->collegeName = $college->name;
            $this->campusId = (int) $college->campus_id;
            $this->campusName = $college->campus?->name ?? '-';

            $this->setDepartmentOptions();
        }

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

    #[Computed]
    public function stats(): array
    {
        $baseQuery = Room::query()->when($this->scope === 'college', fn($query) => $query->where('college_id', $this->collegeId), fn($query) => $query->where('department_id', $this->departmentId));

        return [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('is_active', true)->count(),
            'useable' => (clone $baseQuery)->where('status', Room::toDatabaseStatusValue(RoomStatusEnum::USEABLE->value))->count(),
        ];
    }

    #[On('openEditRoomModal')]
    public function openEditRoomModal(Room $room): void
    {
        $this->ensureCanManage('rooms.update');

        abort_unless($this->canManageRoom($room), 404);

        $this->resetValidation();
        $this->isEditing = true;
        $room->loadMissing(['campus', 'college', 'department']);
        $this->syncContextFromDepartment($room->department);
        $this->form->setRoom($room);
        $this->roomModal = true;
    }

    public function closeRoomModal(): void
    {
        $this->roomModal = false;
        $this->isEditing = false;
        $this->resetValidation();
        $this->syncContextFromDepartment($this->currentDepartment());
        $this->form->resetForm($this->campusId, $this->collegeId, $this->departmentId);
    }

    public function save(): void
    {
        $this->ensureCanManage($this->isEditing ? 'rooms.update' : 'rooms.create');

        $validated = $this->form->validateForm();

        if ($this->scope === 'department') {
            $validated['campus_id'] = $this->campusId;
            $validated['college_id'] = $this->collegeId;
            $validated['department_id'] = $this->departmentId;
        } else {
            $selectedDepartment = Department::query()
                ->with(['campus', 'college'])
                ->where('college_id', $this->collegeId)
                ->findOrFail((int) ($validated['department_id'] ?? $this->departmentId));

            $validated['campus_id'] = (int) $selectedDepartment->campus_id;
            $validated['college_id'] = (int) $selectedDepartment->college_id;
            $validated['department_id'] = (int) $selectedDepartment->id;

            $this->syncContextFromDepartment($selectedDepartment);
            $this->form->setContext($this->campusId, $this->collegeId, $this->departmentId);
        }

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
        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile', 'facultyProfile']);
        $departmentId = $user?->employeeProfile?->department_id ?? $user?->facultyProfile?->department_id;

        if (filled($departmentId)) {
            return Department::query()
                ->with(['campus', 'college'])
                ->findOrFail($departmentId);
        }

        abort_unless($this->scope === 'college', 403);

        return Department::query()
            ->with(['campus', 'college'])
            ->where('college_id', $this->currentCollege()->id)
            ->orderBy('name')
            ->firstOrFail();
    }

    protected function currentCollege(): College
    {
        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile.college', 'employeeProfile.campus', 'facultyProfile.college', 'facultyProfile.campus']);
        $profile = $user?->employeeProfile ?? $user?->facultyProfile;

        $query = College::query()->with('campus')->orderBy('name');

        if (filled($profile?->college_id)) {
            return $query->findOrFail($profile->college_id);
        }

        abort(403);
    }

    protected function setDepartmentOptions(): void
    {
        $this->departmentOptions = Department::query()
            ->where('college_id', $this->collegeId)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(
                fn(Department $department) => [
                    'label' => filled($department->code) ? $department->code . ' - ' . $department->name : $department->name,
                    'value' => (int) $department->id,
                ],
            )
            ->values()
            ->toArray();
    }

    protected function syncContextFromSelectedDepartment(int $departmentId): void
    {
        $department = Department::query()
            ->with(['campus', 'college'])
            ->where('college_id', $this->collegeId)
            ->findOrFail($departmentId);

        $this->syncContextFromDepartment($department);
        $this->form->setContext($this->campusId, $this->collegeId, $this->departmentId);
    }

    public function updatedFormDepartmentId($value): void
    {
        if ($this->scope === 'college' && filled($value)) {
            $this->syncContextFromSelectedDepartment((int) $value);
        }
    }

    protected function syncContextFromDepartment(Department $department): void
    {
        $department->loadMissing(['campus', 'college']);

        $this->campusId = (int) $department->campus_id;
        $this->collegeId = (int) $department->college_id;
        $this->departmentId = (int) $department->id;
        $this->campusName = $department->campus?->name ?? '-';
        $this->collegeCode = $department->college?->code ?? '-';
        $this->collegeName = $department->college?->name ?? '-';
        $this->departmentCode = $department->code ?? '-';
        $this->departmentName = $department->name;
    }

    protected function canManageRoom(Room $room): bool
    {
        if ($this->scope === 'college') {
            return (int) $room->college_id === (int) $this->collegeId;
        }

        return (int) $room->department_id === $this->departmentId;
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold dark:text-white">
                    {{ $scope === 'college' ? $collegeCode : $departmentCode }}
                </h1>
                <x-badge :text="$scope === 'college' ? 'College Scope' : 'Department Scope'" color="blue" round />
            </div>
            <p class="italic text-zinc-600 dark:text-zinc-200">
                @if ($scope === 'college')
                    Managing rooms under {{ $collegeName }}, {{ $campusName }}.
                @else
                    Managing rooms for {{ $departmentName }} under {{ $collegeName }}, {{ $campusName }}.
                @endif
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Rooms</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
                </div>

                <div class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-icon icon="home-modern" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->stats['active'] }}</p>
                </div>

                <div class="rounded-lg bg-green-50 p-2 text-green-600 dark:bg-green-950/40 dark:text-green-300">
                    <x-icon icon="check-circle" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Useable</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['useable'] }}</p>
                </div>

                <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <x-icon icon="hand-thumb-up" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
    </div>

    <x-card>
        <div class="flex flex-col gap-4 border-b border-zinc-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold dark:text-white">Room List</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Review available rooms and update assignments for the current scope.
                </p>
            </div>

            <div class="flex gap-2">
                @can('rooms.create')
                    <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray" text="Import Rooms" />
                    <x-button wire:click="create" sm color="primary" icon="plus" text="New Room" />
                @endcan
            </div>
        </div>

        <div class="p-6">
            <livewire:tables.admin.rooms-table :scope="$scope" :college-id="$collegeId" :department-id="$departmentId" />
        </div>
    </x-card>

    <x-modal wire="roomModal" title="{{ $isEditing ? 'Edit Room' : 'New Room' }}" size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Campus" :value="$campusName" disabled />
                <x-input label="College" :value="filled($collegeCode) && $collegeCode !== '-' ? $collegeCode . ' - ' . $collegeName : $collegeName" disabled />
                @if ($scope === 'college')
                    <x-select.styled label="Department" wire:model="form.department_id" :options="$departmentOptions"
                        select="label:label|value:value" />
                @else
                    <x-input label="Department" :value="filled($departmentCode) && $departmentCode !== '-' ? $departmentCode . ' - ' . $departmentName : $departmentName" disabled />
                @endif
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
                Imported rooms will be assigned to
                {{ filled($departmentCode) && $departmentCode !== '-' ? $departmentCode . ' - ' . $departmentName : $departmentName }}
                automatically.
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
