<?php

use App\Imports\FacultyProfilesImport;
use App\Livewire\Forms\Admin\FacultyProfileForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use Illuminate\Support\Collection;
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

    public Collection $campuses;

    public Collection $colleges;

    public Collection $departments;

    public function mount()
    {
        $this->campuses = Campus::where('is_active', true)->orderBy('name')->get();
        $this->colleges = collect();
        $this->departments = collect();
    }

    public function create()
    {
        $this->form->reset();
        $this->colleges = collect();
        $this->departments = collect();
        $this->createModal = true;
    }

    public function updatedFormCampusId($campusId)
    {
        $this->colleges = filled($campusId) ? College::where('campus_id', $campusId)->where('is_active', true)->orderBy('name')->get() : collect();
        $this->departments = collect();
        $this->form->college_id = null;
        $this->form->department_id = null;
    }

    public function updatedFormCollegeId($collegeId)
    {
        $this->departments = filled($collegeId) ? Department::where('college_id', $collegeId)->where('is_active', true)->orderBy('name')->get() : collect();
        $this->form->department_id = null;
    }

    public function save()
    {
        $this->form->store();
        $this->createModal = false;
        $this->form->reset();
        $this->colleges = collect();
        $this->departments = collect();
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
};
?>

<div class="">
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-xl font-bold dark:text-white">Faculty Profiles</h1>
        <div class="flex gap-2">
            <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray" text="Import Faculty" />
            <x-button wire:click="create" sm color="primary" icon="plus" text="New Faculty" />
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.faculty-profiles-table />
    </div>

    <x-modal wire="createModal" title="New Faculty">
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
                <x-select.styled label="Campus" wire:model.live="form.campus_id" :options="$campuses->map(fn($campus) => ['label' => $campus->name, 'value' => $campus->id])->toArray()"
                    select="label:label|value:value" />

                <x-select.styled label="College" wire:model.live="form.college_id" :options="$colleges
                    ->map(fn($college) => ['label' => $college->name, 'value' => $college->id])
                    ->toArray()"
                    select="label:label|value:value" />

                <x-select.styled label="Department" wire:model="form.department_id" :options="$departments
                    ->map(fn($department) => ['label' => $department->name, 'value' => $department->id])
                    ->toArray()"
                    select="label:label|value:value" :disabled="$colleges->isEmpty()" />
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
            <p class="text-xs text-zinc-500">Headers needed: first_name, middle_name, last_name, email, campus_id,
                college_id, department_id, academic_rank, contactno, sex, birthday, address, password</p>
        </div>
        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('importModal', false)" />
            <x-button color="primary" text="Upload & Import" wire:click="import" wire:loading.attr="disabled" />
        </x-slot:footer>
    </x-modal>
</div>
