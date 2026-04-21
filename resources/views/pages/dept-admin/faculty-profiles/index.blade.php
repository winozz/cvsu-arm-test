<?php

use App\Imports\FacultyProfilesImport;
use App\Livewire\Forms\Admin\FacultyProfileForm;
use App\Models\Campus;
use App\Models\FacultyProfile;
use App\Models\User;
use App\Traits\CanManage;
use App\Traits\HasCascadingLocationSelects;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions, WithFileUploads, HasCascadingLocationSelects;

    public FacultyProfileForm $form;

    public bool $createModal = false;

    public bool $importModal = false;

    public $importFile;

    public array $colleges = [];

    public array $departments = [];

    #[Computed]
    public function campuses()
    {
        return Campus::where('is_active', true)->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['label' => $c->name, 'value' => $c->id])
            ->toArray();
    }

    public function mount()
    {
        $this->ensureCanManage('faculty_profiles.view');

        $this->colleges = [];
        $this->departments = [];
    }

    public function create()
    {
        $this->ensureCanManage('faculty_profiles.create');

        $this->form->reset();
        $this->colleges = [];
        $this->departments = [];
        $this->createModal = true;
    }

    public function save()
    {
        $this->ensureCanManage('faculty_profiles.create');

        $this->form->validateForm();

        $assignment = $this->form->resolveAcademicAssignment();

        $user = User::create([
            'name' => $this->form->fullName(),
            'email' => $this->form->email,
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->assignRole('faculty');

        FacultyProfile::create(
            array_merge($assignment, [
                'user_id' => $user->id,
                'first_name' => $this->form->first_name,
                'middle_name' => $this->form->middle_name,
                'last_name' => $this->form->last_name,
                'email' => $this->form->email,
                'academic_rank' => $this->form->academic_rank,
                'contactno' => $this->form->contactno,
                'sex' => $this->form->sex,
                'birthday' => $this->form->birthday ?: null,
                'address' => $this->form->address,
            ]),
        );

        $this->createModal = false;
        $this->form->resetForm();
        $this->colleges = collect();
        $this->departments = collect();
        $this->toast()->success('Success', 'Faculty Profile created successfully.')->send();
        $this->dispatch('pg:eventRefresh-facultyProfilesTable');
    }

    public function import()
    {
        $this->ensureCanManage('faculty_profiles.create');

        $this->validate(['importFile' => 'required|mimes:csv,xlsx,xls']);
        Excel::import(new FacultyProfilesImport(), $this->importFile);

        $this->importModal = false;
        $this->importFile = null;
        $this->toast()->success('Success', 'Faculty imported successfully.')->send();
        $this->dispatch('pg:eventRefresh-facultyProfilesTable');
    }
};
?>

<div>
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-xl font-bold dark:text-white">Faculty Profiles</h1>
        <div class="flex gap-2">
            @can('faculty_profiles.create')
                <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray" text="Import Faculty" />
                <x-button wire:click="create" sm color="primary" icon="plus" text="New Faculty" />
            @endcan
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
                <x-select.styled label="Campus" wire:model.live="form.campus_id" :options="$this->campuses"
                    select="label:label|value:value" />

                <x-select.styled label="College" wire:model.live="form.college_id" :options="$colleges"
                    select="label:label|value:value" />

                <x-select.styled label="Department" wire:model="form.department_id" :options="$departments"
                    select="label:label|value:value" :disabled="empty($colleges)" />
            </div>

            <x-input label="Academic Rank" wire:model="form.academic_rank" />
            <x-textarea label="Address" wire:model="form.address" />
        </div>

        <x-slot:footer>
            @can('faculty_profiles.create')
                <x-button flat text="Cancel" wire:click="$set('createModal', false)" />
                <x-button color="primary" text="Save Faculty" wire:click="save" />
            @endcan
        </x-slot:footer>
    </x-modal>

    <x-modal wire="importModal" title="Import Faculty Profiles">
        <div class="space-y-4">
            <x-upload wire:model="importFile" label="Select Excel/CSV File" hint="Supported files: .xlsx, .csv" />
            <p class="text-xs text-zinc-500">Headers needed: first_name, middle_name, last_name, email, campus_id,
                college_id, department_id, academic_rank, contactno, sex, birthday, address, password</p>
        </div>
        <x-slot:footer>
            @can('faculty_profiles.create')
                <x-button flat text="Cancel" wire:click="$set('importModal', false)" />
                <x-button color="primary" text="Upload & Import" wire:click="import" wire:loading.attr="disabled" />
            @endcan
        </x-slot:footer>
    </x-modal>
</div>
