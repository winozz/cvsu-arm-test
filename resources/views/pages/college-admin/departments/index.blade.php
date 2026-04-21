<?php

use App\Livewire\Forms\Admin\CollegeForm;
use App\Livewire\Forms\Admin\DepartmentForm;
use App\Models\Campus;
use App\Models\College;
use App\Traits\CanManage;
use App\Traits\HasDepartmentManagement;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions, HasDepartmentManagement;

    public College $college;

    public Campus $campus;

    public CollegeForm $collegeForm;

    public DepartmentForm $departmentForm;

    public function mount(): void
    {
        $this->ensureCanManage('departments.view');

        $user = auth()
            ->user()
            ?->loadMissing(['employeeProfile.campus', 'employeeProfile.college']);
        $profile = $user?->employeeProfile;

        if ($profile?->campus && $profile?->college) {
            abort_unless((int) $profile->college->campus_id === (int) $profile->campus->id, 403);

            $this->campus = $profile->campus;
            $this->college = $profile->college;
            $this->syncDepartmentContextForms();

            return;
        }

        abort_unless($user?->hasRole('superAdmin'), 403);

        $this->resolveFallbackCollegeContext();
        $this->syncDepartmentContextForms();
    }
};

?>

<div>
    <div
        class="flex flex-col items-start justify-between gap-4 p-6 mb-6 bg-white rounded-lg shadow md:flex-row md:items-center dark:bg-gray-800">
        <div>
            <h3 class="text-xl font-medium dark:text-white">{{ $college->code }}</h3>
            <p class="italic text-zinc-600 dark:text-zinc-200">{{ $college->name }}</p>

            <div class="mt-2">
                <span
                    class="px-2 py-1 text-xs font-semibold rounded-full {{ $college->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $college->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
        <div class="flex gap-2">
            @can('colleges.update')
                <x-button wire:click="editCollege" sm color="primary" icon="pencil" text="Edit Details" />
            @endcan

            @can('departments.view')
                <x-button tag="a" href="{{ route('college-admin.dashboard') }}" sm outline
                    text="Back to Dashboard" />
            @endcan
        </div>
    </div>

    <div
        class="flex flex-col items-start justify-between gap-4 px-6 py-4 mb-6 bg-white rounded-lg shadow md:flex-row md:items-center dark:bg-gray-800">
        <h1 class="text-2xl font-bold dark:text-white">Department List</h1>
        @can('departments.create')
            <x-button wire:click="openCreateDepartmentModal" sm color="primary" icon="plus" text="New Department" />
        @endcan
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.departments-table :college-id="$college->id" />
    </div>

    <x-modal wire="collegeModal" title="Edit College Details" size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="College Code" wire:model="collegeForm.code" hint="Use a short code like CEIT or CAS." />
                <x-input label="College Name" wire:model="collegeForm.name" />
            </div>

            <x-input label="Campus" :value="$campus->code . ' - ' . $campus->name" disabled
                hint="This college belongs to your assigned campus and cannot be changed here." />

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
