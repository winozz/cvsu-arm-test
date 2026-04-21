<?php

use App\Livewire\Forms\Admin\FacultyProfileUpdateForm;
use App\Models\Campus;
use App\Models\FacultyProfile;
use App\Traits\CanManage;
use App\Traits\HasCascadingLocationSelects;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions, HasCascadingLocationSelects;

    public FacultyProfile $facultyProfile;

    public bool $isEditing = false;

    public FacultyProfileUpdateForm $form;

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

    public function mount(FacultyProfile $facultyProfile)
    {
        $this->ensureCanManage('faculty_profiles.view');

        $this->facultyProfile = $facultyProfile->load(['user', 'campus', 'college', 'department']);
        $this->form->setValues($this->facultyProfile);
        $this->refreshAssignmentOptions();
    }

    public function confirmEdit()
    {
        $this->ensureCanManage('faculty_profiles.update');

        if (!$this->isEditing) {
            $this->dialog()->question('Enable Editing?', 'Do you want to modify this faculty profile?')->confirm('Yes', 'enableEditing')->send();

            return;
        }

        $this->isEditing = false;
    }

    public function enableEditing()
    {
        $this->ensureCanManage('faculty_profiles.update');

        $this->isEditing = true;
    }

    public function confirmSave()
    {
        $this->ensureCanManage('faculty_profiles.update');

        $this->dialog()->question('Save Changes?', 'Update this faculty profile and user record?')->confirm('Yes', 'save')->send();
    }

    public function save()
    {
        $this->ensureCanManage('faculty_profiles.update');

        $this->form->validateForm();

        $assignment = $this->form->resolveAcademicAssignment();

        $this->form->profile->update([
            'first_name' => $this->form->first_name,
            'middle_name' => $this->form->middle_name,
            'last_name' => $this->form->last_name,
            'email' => $this->form->email,
            'campus_id' => $assignment['campus_id'],
            'college_id' => $assignment['college_id'],
            'department_id' => $assignment['department_id'],
            'academic_rank' => $this->form->academic_rank,
            'contactno' => $this->form->contactno,
            'address' => $this->form->address,
            'sex' => $this->form->sex,
            'birthday' => $this->form->birthday ?: null,
        ]);

        if ($this->form->profile->user) {
            $this->form->profile->user->update([
                'name' => $this->form->fullName(),
                'email' => $this->form->email,
            ]);
        }

        $this->facultyProfile->refresh()->load(['user', 'campus', 'college', 'department']);
        $this->form->setValues($this->facultyProfile);
        $this->refreshAssignmentOptions();
        $this->isEditing = false;
        $this->toast()->success('Faculty Profile updated successfully.')->send();
    }
}; ?>

<div class="py-8">
    <x-toast />
    <x-dialog />

    <div class="mb-6 flex justify-between items-center bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <div class="flex items-center gap-4">
            <div
                class="w-16 h-16 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xl font-bold dark:bg-zinc-700 dark:text-zinc-200">
                {{ strtoupper(substr($form->first_name, 0, 1) . substr($form->last_name, 0, 1)) }}
            </div>
            <div>
                <h1 class="text-2xl font-bold dark:text-white">{{ $form->first_name }} {{ $form->last_name }}</h1>
                <p class="text-zinc-500 dark:text-zinc-400">{{ $form->email }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            @can('faculty_profiles.update')
                <x-button wire:click="confirmEdit" sm :color="$isEditing ? 'red' : 'primary'" :text="$isEditing ? 'Cancel' : 'Edit Profile'" :icon="$isEditing ? 'x-mark' : 'pencil'" />
            @endcan
            @can('faculty_profiles.view')
                <x-button tag="a" href="{{ route('department-admin.faculty-profiles') }}" sm outline
                    text="Back to List" />
            @endcan
        </div>
    </div>

    <div class="bg-white p-8 rounded-lg shadow dark:bg-zinc-800">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-input label="First Name" wire:model="form.first_name" :disabled="!$isEditing" />
            <x-input label="Middle Name" wire:model="form.middle_name" :disabled="!$isEditing" />
            <x-input label="Last Name" wire:model="form.last_name" :disabled="!$isEditing" />

            <x-input label="Email Address" wire:model="form.email" :disabled="!$isEditing" />
            <x-input label="Contact No" wire:model="form.contactno" :disabled="!$isEditing" />
            <x-input label="Academic Rank" wire:model="form.academic_rank" :disabled="!$isEditing" />

            <x-select.styled label="Campus" wire:model.live="form.campus_id" :disabled="!$isEditing" :options="$this->campuses"
                select="label:label|value:value" />

            <x-select.styled label="College" wire:model.live="form.college_id" :disabled="!$isEditing" :options="$colleges"
                select="label:label|value:value" />

            <x-select.styled label="Department" wire:model="form.department_id" :disabled="!$isEditing" :options="$departments"
                select="label:label|value:value" />

            <x-select.styled label="Sex" wire:model="form.sex" :disabled="!$isEditing" :options="['Male', 'Female']" />

            <x-input label="Birthday" type="date" wire:model="form.birthday" :disabled="!$isEditing" />

            <div class="md:col-span-2">
                <x-textarea label="Address" wire:model="form.address" :disabled="!$isEditing" />
            </div>
        </div>

        @if ($isEditing)
            <div class="mt-8 flex justify-end">
                @can('faculty_profiles.update')
                    <x-button wire:click="confirmSave" color="primary" text="Save Changes" icon="check" />
                @endcan
            </div>
        @endif
    </div>
</div>
