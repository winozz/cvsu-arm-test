<?php

use App\Livewire\Forms\Admin\BranchDepartmentForm;
use App\Livewire\Forms\Admin\BranchForm;
use App\Models\Branch;
use App\Models\Department;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use Interactions;

    public Branch $branch;

    public BranchForm $form;

    public BranchDepartmentForm $departmentForm;

    public bool $editModal = false;

    public bool $departmentModal = false;

    public bool $isEditingDepartment = false;

    public function mount(Branch $branch): void
    {
        $this->branch = $branch;
        $this->form->setBranch($branch);
    }

    public function edit(): void
    {
        $this->form->setBranch($this->branch);
        $this->editModal = true;
    }

    public function save(): void
    {
        $this->form->store();
        $this->branch->refresh();
        $this->editModal = false;
        $this->toast()->success('Success', 'Branch updated successfully.')->send();
    }

    // --- Department Management ---

    public function createDepartment(): void
    {
        $this->departmentForm->reset();
        $this->departmentForm->branch_id = $this->branch->id;
        $this->isEditingDepartment = false;
        $this->departmentModal = true;
    }

    #[On('editDepartment')]
    public function editDepartment(Department $department): void
    {
        $this->departmentForm->setDepartment($department);
        $this->isEditingDepartment = true;
        $this->departmentModal = true;
    }

    public function saveDepartment(): void
    {
        // Enforce the branch_id capture before saving
        $this->departmentForm->branch_id = $this->branch->id;

        if ($this->isEditingDepartment) {
            $this->departmentForm->update();
            $this->toast()->success('Success', 'Department updated successfully.')->send();
        } else {
            $this->departmentForm->store();
            $this->toast()->success('Success', 'Department created successfully.')->send();
        }

        $this->departmentModal = false;
        $this->dispatch('pg:eventRefresh-branchDepartmentsTable');
    }

    #[On('confirmDelete')]
    public function confirmDelete($id): void
    {
        $deptId = is_array($id) ? $id['id'] : $id;
        $this->dialog()->question('Warning!', 'Are you sure you want to delete this department?')->confirm('Yes, delete', 'deleteDepartment', $deptId)->cancel('Cancel')->send();
    }

    public function deleteDepartment($id): void
    {
        Department::findOrFail($id)->delete();
        $this->toast()->success('Deleted', 'Department moved to trash.')->send();
        $this->dispatch('pg:eventRefresh-branchDepartmentsTable');
    }

    #[On('confirmRestore')]
    public function confirmRestore($id): void
    {
        $deptId = is_array($id) ? $id['id'] : $id;
        $this->dialog()->question('Restore?', 'Are you sure you want to restore this department?')->confirm('Yes, restore', 'restoreDepartment', $deptId)->cancel('Cancel')->send();
    }

    public function restoreDepartment($id): void
    {
        Department::withTrashed()->findOrFail($id)->restore();
        $this->toast()->success('Restored', 'Department has been restored.')->send();
        $this->dispatch('pg:eventRefresh-branchDepartmentsTable');
    }
};
?>

<div class="max-w-7xl mx-auto py-8">
    {{-- Header & Campus Information --}}
    <div
        class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-lg shadow dark:bg-gray-800">
        <div>
            <h1 class="text-2xl font-bold dark:text-white">{{ $branch->code }}</h1>
            <p class="italic text-zinc-600 dark:text-zinc-200">{{ $branch->name }} </p>
            <p class="text-gray-600 dark:text-gray-300 mt-1">{{ $branch->type }} Campus | {{ $branch->address }}</p>
            <div class="mt-2">
                <span
                    class="px-2 py-1 text-xs font-semibold rounded-full {{ $branch->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $branch->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
        <div class="flex gap-2">
            <x-button wire:click="edit" sm color="primary" icon="pencil" text="Edit Details" />
            <x-button tag="a" href="{{ route('admin.branches') }}" sm outline text="Back to List" />
        </div>
    </div>

    {{-- Departments Section --}}
    <div class="mt-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
            <h2 class="text-xl font-semibold dark:text-white">Departments</h2>
            <x-button wire:click="createDepartment" sm color="primary" icon="plus" text="New Department" />
        </div>

        <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
            {{-- Departments Table --}}
            <livewire:admin.branch-departments-table :branch-id="$branch->id" />
        </div>
    </div>

    {{-- Edit Branch Modal --}}
    <x-modal wire="editModal" title="Edit {{ $branch->name }}">
        <div class="space-y-4">
            <x-input label="Code" wire:model="form.code" />
            <x-input label="Name" wire:model="form.name" />
            <x-select.styled label="Campus Type" wire:model="form.type" :options="['Main', 'Satellite']" />
            <x-textarea label="Address" wire:model="form.address" />
            <x-toggle label="Active" wire:model="form.is_active" />
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('editModal', false)" sm />
            <x-button color="primary" text="Save Changes" wire:click="save" sm />
        </x-slot:footer>
    </x-modal>

    {{-- Reusable Department Modal --}}
    <x-modal wire="departmentModal" title="{{ $isEditingDepartment ? 'Edit Department' : 'New Department' }}">
        <div class="space-y-4">
            <x-input label="Code" wire:model="departmentForm.code" />
            <x-input label="Name" wire:model="departmentForm.name" />
            <x-toggle label="Active" wire:model="departmentForm.is_active" />
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('departmentModal', false)" sm />
            <x-button color="primary" text="Save" wire:click="saveDepartment" sm />
        </x-slot:footer>
    </x-modal>
</div>
