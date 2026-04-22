<?php

use App\Livewire\Forms\Admin\CollegeForm;
use App\Livewire\Forms\Admin\DepartmentForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Traits\CanManage;
use App\Traits\HasDepartmentManagement;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, HasDepartmentManagement, Interactions;

    public College $college;

    public Campus $campus;

    public CollegeForm $collegeForm;

    public DepartmentForm $departmentForm;

    public function mount(Campus $campus, College $college): void
    {
        $this->ensureCanManage('departments.view');

        abort_unless($college->campus_id === $campus->id, 404);

        $this->campus = $campus;
        $this->college = $college;
        $this->syncDepartmentContextForms();
    }

    #[Computed]
    public function departmentStats(): array
    {
        return [
            'total' => Department::query()->where('college_id', $this->college->id)->count(),
            'active' => Department::query()->where('college_id', $this->college->id)->where('is_active', true)->count(),
            'inactive' => Department::query()->where('college_id', $this->college->id)->where('is_active', false)->count(),
        ];
    }
};
?>

<div class="space-y-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('campuses.index') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Campuses</a>
        <span>/</span>
        <a href="{{ route('campuses.show', [$this->campus->id]) }}"
            class="hover:text-primary-600 dark:hover:text-primary-400">{{ $campus->code }}</a>
        <span>/</span>
        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $college->code }}</span>
    </nav>

    {{-- College Info Card --}}
    <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold dark:text-white">{{ $college->code }}</h1>
                <span
                    class="px-2 py-1 text-xs font-semibold rounded-full {{ $college->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $college->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <p class="italic text-zinc-600 dark:text-zinc-200">{{ $college->name }}</p>
        </div>
        <div class="flex gap-2">
            @can('colleges.update')
                <x-button wire:click="editCollege" sm color="primary" icon="pencil" text="Edit Details" />
            @endcan
            @can('colleges.view')
                <x-button tag="a" href="{{ route('campuses.show', [$this->campus->id]) }}" sm outline
                    text="Back to Colleges" />
            @endcan
        </div>
    </div>

    {{-- Department Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Departments</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->departmentStats['total'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active</p>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->departmentStats['active'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Inactive</p>
            <p class="mt-1 text-2xl font-bold text-red-500">{{ $this->departmentStats['inactive'] }}</p>
        </x-card>
    </div>

    {{-- Main Department Body --}}
    <x-card>
        <div class="flex flex-col gap-4 border-b border-zinc-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold dark:text-white">Department List</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Departments under {{ $college->name }}.</p>
            </div>

            @can('departments.create')
                <x-button wire:click="openCreateDepartmentModal" sm color="primary" icon="plus" text="New Department" />
            @endcan
        </div>

        <div class="p-6">
            <livewire:tables.admin.departments-table :college-id="$college->id" />
        </div>
    </x-card>

    <x-modal wire="collegeModal" title="Edit College Details" size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="College Code" wire:model="collegeForm.code" hint="Use a short code like CEIT or CAS." />
                <x-input label="College Name" wire:model="collegeForm.name" />
            </div>

            <x-input label="Campus" :value="$campus->code . ' - ' . $campus->name" disabled
                hint="This college belongs to the selected campus and cannot be changed here." />

            <x-textarea label="Description" wire:model="collegeForm.description"
                hint="Optional short description for this college." />

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="collegeForm.is_active" label="College is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $collegeForm->is_active ? 'This college is available for active assignments.' : 'This college will be marked inactive.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            @can('colleges.update')
                <x-button flat text="Cancel" wire:click="closeCollegeModal" sm />
                <x-button color="primary" text="Save Changes" wire:click="confirmSaveCollege" sm />
            @endcan
        </x-slot:footer>
    </x-modal>

    <x-modal wire="departmentModal" title="{{ $isEditingDepartment ? 'Edit Department Details' : 'New Department' }}"
        size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Department Code" wire:model="departmentForm.code"
                    hint="Use a short code like CEIT-ACAD." />
                <x-input label="Department Name" wire:model="departmentForm.name" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Campus" :value="$campus->code . ' - ' . $campus->name" disabled />
                <x-input label="College" :value="$college->code . ' - ' . $college->name" disabled />
            </div>

            <x-textarea label="Description" wire:model="departmentForm.description"
                hint="Optional short description for this department." />

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="departmentForm.is_active" label="Department is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $departmentForm->is_active ? 'This department is available for active assignments.' : 'This department will be marked inactive.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            @canany(['departments.update', 'departments.create'])
                <x-button flat text="Cancel" wire:click="closeDepartmentModal" sm />
                <x-button color="primary" :text="$isEditingDepartment ? 'Save Changes' : 'Create Department'" wire:click="confirmSaveDepartment" sm />
            @endcanany
        </x-slot:footer>
    </x-modal>

</div>
