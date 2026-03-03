<?php

use App\Imports\FacultyProfilesImport;
use App\Livewire\Forms\Admin\FacultyProfileForm;
use App\Models\Branch;
use App\Models\Department;
use App\Models\FacultyProfile;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use Interactions, WithFileUploads;

    public FacultyProfileForm $form;
    public bool $createModal = false;
    public bool $importModal = false;
    public $importFile;

    public Collection $branches;
    public Collection $departments;

    public function mount()
    {
        $this->branches = Branch::where('is_active', true)->get();
        $this->departments = collect();
    }

    public function updatedFormBranchId($branchId)
    {
        $this->departments = Department::where('branch_id', $branchId)->where('is_active', true)->get();
        $this->form->department_id = null;
    }

    public function save()
    {
        $this->form->store();
        $this->createModal = false;
        $this->toast()->success('Success', 'Faculty Profile created successfully.')->send();
        $this->dispatch('pg:eventRefresh-facultyProfilesTable');
    }

    public function import()
    {
        $this->validate(['importFile' => 'required|mimes:csv,xlsx,xls']);
        Excel::import(new FacultyProfilesImport(), $this->importFile);

        $this->importModal = false;
        $this->importFile = null;
        $this->toast()->success('Success', 'Faculty imported successfully.')->send();
        $this->dispatch('pg:eventRefresh-facultyProfilesTable');
    }

    #[On('confirmDelete')]
    public function confirmDelete($id): void
    {
        $profileId = is_array($id) ? $id['id'] : $id;
        $this->dialog()->question('Warning!', 'Are you sure you want to delete this faculty profile?')->confirm('Yes, delete', 'deleteProfile', $profileId)->cancel('Cancel')->send();
    }

    public function deleteProfile($id): void
    {
        FacultyProfile::findOrFail($id)->delete();
        $this->toast()->success('Deleted', 'Faculty Profile moved to trash.')->send();
        $this->dispatch('pg:eventRefresh-facultyProfilesTable');
    }

    #[On('confirmRestore')]
    public function confirmRestore($id): void
    {
        $profileId = is_array($id) ? $id['id'] : $id;
        $this->dialog()->question('Restore?', 'Are you sure you want to restore this faculty profile?')->confirm('Yes, restore', 'restoreProfile', $profileId)->cancel('Cancel')->send();
    }

    public function restoreProfile($id): void
    {
        FacultyProfile::withTrashed()->findOrFail($id)->restore();
        $this->toast()->success('Restored', 'Faculty Profile has been restored.')->send();
        $this->dispatch('pg:eventRefresh-facultyProfilesTable');
    }
};
?>

<div class="max-w-7xl mx-auto py-8">
    <x-toast />
    <x-dialog />

    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-xl font-bold dark:text-white">Faculty Profiles</h1>
        <div class="flex gap-2">
            <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray" text="Import Faculty" />
            <x-button wire:click="$set('createModal', true)" sm color="primary" icon="plus" text="Add Faculty" />
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.faculty-profiles-table />
    </div>

    <x-modal wire="createModal" title="Add New Faculty">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-input label="First Name" wire:model="form.first_name" />
                <x-input label="Middle Name" wire:model="form.middle_name" />
                <x-input label="Last Name" wire:model="form.last_name" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Email Address" type="email" wire:model="form.email" />
                <x-input label="Contact No" wire:model="form.contactno" />

                <x-select.styled label="Sex" wire:model="form.sex" :options="['Male', 'Female']" />
                <x-input label="Birthday" type="date" wire:model="form.birthday" />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-select.styled label="Campus / Branch" wire:model.live="form.branch_id" :options="$branches->map(fn($b) => ['label' => $b->name, 'value' => $b->id])->toArray()"
                    select="label:label|value:value" />

                @if ($departments->isNotEmpty())
                    <x-select.styled label="Department" wire:model="form.department_id" :options="$departments->map(fn($d) => ['label' => $d->name, 'value' => $d->id])->toArray()"
                        select="label:label|value:value" />
                @endif
            </div>

            <x-input label="Academic Rank" wire:model="form.academic_rank" />
            <x-textarea label="Address" wire:model="form.address" />
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('createModal', false)" />
            <x-button color="primary" text="Save Faculty" wire:click="save" />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="importModal" title="Import Faculty Profiles">
        <div class="space-y-4">
            <x-upload wire:model="importFile" label="Select Excel/CSV File" hint="Supported files: .xlsx, .csv" />
            <p class="text-xs text-zinc-500">Headers needed: first_name, middle_name, last_name, email, branch_id,
                department_id, academic_rank, contactno, sex, birthday, address, password</p>
        </div>
        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('importModal', false)" />
            <x-button color="primary" text="Upload & Import" wire:click="import" wire:loading.attr="disabled" />
        </x-slot:footer>
    </x-modal>
</div>
