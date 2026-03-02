<?php

use App\Imports\UsersImport;
use App\Livewire\Forms\Admin\UsersForm;
use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions, WithFileUploads;

    public UsersForm $form;
    public bool $createModal = false;
    public bool $importModal = false;
    public $importFile;

    public Collection $branches;
    public Collection $departments;
    public Collection $roles;

    public function mount()
    {
        $this->branches = Branch::where('is_active', true)->get();
        $this->departments = collect();
        $this->roles = Role::all();
    }

    public function updatedFormBranchId($branchId)
    {
        // When Branch changes, fetch departments
        $this->departments = Department::where('branch_id', $branchId)->where('is_active', true)->get();
        $this->form->department_id = null; 
    }

    public function save()
    {
        $this->form->store();
        $this->createModal = false;
        $this->toast()->success('Success', 'User created successfully.')->send();
        $this->dispatch('pg:eventRefresh-usersTable');
    }

    public function import()
    {
        $this->validate(['importFile' => 'required|mimes:csv,xlsx,xls']);
        Excel::import(new UsersImport, $this->importFile);
        
        $this->importModal = false;
        $this->importFile = null;
        $this->toast()->success('Success', 'Users imported successfully.')->send();
        $this->dispatch('pg:eventRefresh-usersTable');
    }

    #[On('confirmDelete')]
    public function confirmDelete($id): void
    {
        $userId = is_array($id) ? $id['id'] : $id;
        $this->dialog()->question('Warning!', 'Are you sure you want to delete this user?')
            ->confirm('Yes, delete', 'deleteUser', $userId)
            ->cancel('Cancel')
            ->send();
    }

    public function deleteUser($id): void
    {
        User::findOrFail($id)->delete();
        $this->toast()->success('Deleted', 'User moved to trash.')->send();
        $this->dispatch('pg:eventRefresh-usersTable');
    }

    #[On('confirmRestore')]
    public function confirmRestore($id): void
    {
        $userId = is_array($id) ? $id['id'] : $id;
        $this->dialog()->question('Restore?', 'Are you sure you want to restore this user?')
            ->confirm('Yes, restore', 'restoreUser', $userId)
            ->cancel('Cancel')
            ->send();
    }

    public function restoreUser($id): void
    {
        User::withTrashed()->findOrFail($id)->restore();
        $this->toast()->success('Restored', 'User has been restored.')->send();
        $this->dispatch('pg:eventRefresh-usersTable');
    }
};
?>

<div class="max-w-7xl mx-auto py-8">
    <x-toast />
    <x-dialog />

    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold dark:text-white">Users Management</h1>
        <div class="flex gap-2">
            <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray" text="Import" />
            <x-button wire:click="$set('createModal', true)" sm color="primary" icon="plus" text="Add User" />
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
        <livewire:admin.users-table />
    </div>

    <x-modal wire="createModal" title="Add New User">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="First Name" wire:model="form.first_name" />
                <x-input label="Last Name" wire:model="form.last_name" />
            </div>

            <x-input label="Email Address" type="email" wire:model="form.email" />

            {{-- Formatted specifically for TallStack UI --}}
            <x-select.styled label="Roles" wire:model="form.roles" multiple
                :options="$roles->map(fn($r) => ['label' => $r->name, 'value' => $r->name])->toArray()"
                select="label:label|value:value" />

            {{-- Formatted specifically for TallStack UI --}}
            <x-select.styled label="Profile Type" wire:model="form.type"
                :options="[['label' => 'Faculty', 'value' => 'faculty'], ['label' => 'Employee', 'value' => 'employee']]"
                select="label:label|value:value" />

            {{-- Formatted specifically for TallStack UI --}}
            <x-select.styled label="Campus / Branch" wire:model.live="form.branch_id"
                :options="$branches->map(fn($b) => ['label' => $b->name, 'value' => $b->id])->toArray()"
                select="label:label|value:value" />

            @if($departments->isNotEmpty())
            {{-- Formatted specifically for TallStack UI --}}
            <x-select.styled label="Department" wire:model="form.department_id"
                :options="$departments->map(fn($d) => ['label' => $d->name, 'value' => $d->id])->toArray()"
                select="label:label|value:value" />
            @endif
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('createModal', false)" />
            <x-button color="primary" text="Save User" wire:click="save" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="importModal" title="Import Users">
        <div class="space-y-4">
            {{-- <input type="file" wire:model="importFile" class="block w-full text-sm text-gray-500
                file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0
                file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700
                hover:file:bg-primary-100 dark:text-gray-300 dark:file:bg-gray-700 dark:file:text-gray-200" /> --}}


            <x-upload wire:model="importFile" label="Select Excel/CSV File" hint="Supported files: .xlsx, .csv" />
        </div>
        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('importModal', false)" />
            <x-button color="primary" text="Upload & Import" wire:click="import" wire:loading.attr="disabled" />
        </x-slot:footer>
    </x-modal>
</div>