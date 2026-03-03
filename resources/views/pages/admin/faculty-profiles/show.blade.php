<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\FacultyProfile;
use Illuminate\Support\Collection;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use Interactions;

    public FacultyProfile $facultyProfile;
    public bool $isEditing = false;

    // Form fields
    public $first_name = '';
    public $middle_name = '';
    public $last_name = '';
    public $email = '';
    public $branch_id = null;
    public $department_id = null;
    public $academic_rank = '';
    public $contactno = '';
    public $address = '';
    public $sex = '';
    public $birthday = '';

    public Collection $branches;
    public Collection $departments;

    public function mount(FacultyProfile $facultyProfile)
    {
        $this->facultyProfile = $facultyProfile->load(['user', 'branch', 'department']);
        $this->branches = Branch::where('is_active', true)->get();
        $this->departments = collect();

        $this->first_name = $this->facultyProfile->first_name;
        $this->middle_name = $this->facultyProfile->middle_name;
        $this->last_name = $this->facultyProfile->last_name;
        $this->email = $this->facultyProfile->email;
        $this->branch_id = $this->facultyProfile->branch_id;
        $this->department_id = $this->facultyProfile->department_id;
        $this->academic_rank = $this->facultyProfile->academic_rank;
        $this->contactno = $this->facultyProfile->contactno;
        $this->address = $this->facultyProfile->address;
        $this->sex = $this->facultyProfile->sex;
        $this->birthday = $this->facultyProfile->birthday;

        if ($this->branch_id) {
            $this->departments = Department::where('branch_id', $this->branch_id)->get();
        }
    }

    public function updatedBranchId($value)
    {
        $this->departments = Department::where('branch_id', $value)->get();
        $this->department_id = null;
    }

    public function confirmEdit()
    {
        if ($this->isEditing) {
            $this->isEditing = false;
            return;
        }
        $this->dialog()->question('Enable Editing?', 'Do you want to modify this faculty profile?')->confirm('Yes', 'enableEditing')->send();
    }

    public function enableEditing()
    {
        $this->isEditing = true;
    }

    public function confirmSave()
    {
        $this->dialog()->question('Save Changes?', 'Update this faculty profile and user record?')->confirm('Yes', 'save')->send();
    }

    public function save()
    {
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:faculty_profiles,email,' . $this->facultyProfile->id,
            'branch_id' => 'required|exists:branches,id',
            'department_id' => 'required|exists:departments,id',
            'sex' => 'nullable|in:Male,Female',
            'birthday' => 'nullable|date',
        ]);

        $fullName = trim($this->first_name . ' ' . ($this->middle_name ? $this->middle_name . ' ' : '') . $this->last_name);

        // Update the Profile
        $this->facultyProfile->update([
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'academic_rank' => $this->academic_rank,
            'contactno' => $this->contactno,
            'address' => $this->address,
            'sex' => $this->sex,
            'birthday' => $this->birthday,
        ]);

        // Keep the underlying User record synced
        if ($this->facultyProfile->user) {
            $this->facultyProfile->user->update([
                'name' => $fullName,
                'email' => $this->email,
            ]);
        }

        $this->facultyProfile->refresh();
        $this->isEditing = false;
        $this->toast()->success('Faculty Profile updated successfully.')->send();
    }
}; ?>

<div class="max-w-5xl mx-auto py-8">
    <x-toast />
    <x-dialog />

    <div class="mb-6 flex justify-between items-center bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <div class="flex items-center gap-4">
            <div
                class="w-16 h-16 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xl font-bold dark:bg-zinc-700 dark:text-zinc-200">
                {{ strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) }}
            </div>
            <div>
                <h1 class="text-2xl font-bold dark:text-white">{{ $first_name }} {{ $last_name }}</h1>
                <p class="text-zinc-500 dark:text-zinc-400">{{ $email }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <x-button wire:click="confirmEdit" sm :color="$isEditing ? 'red' : 'primary'" :text="$isEditing ? 'Cancel' : 'Edit Profile'" :icon="$isEditing ? 'x-mark' : 'pencil'" />
            <x-button tag="a" href="{{ route('admin.faculty-profiles') }}" sm outline text="Back to List" />
        </div>
    </div>

    <div class="bg-white p-8 rounded-lg shadow dark:bg-zinc-800">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-input label="First Name" wire:model="first_name" :disabled="!$isEditing" />
            <x-input label="Middle Name" wire:model="middle_name" :disabled="!$isEditing" />
            <x-input label="Last Name" wire:model="last_name" :disabled="!$isEditing" />

            <x-input label="Email Address" wire:model="email" :disabled="!$isEditing" />
            <x-input label="Contact No" wire:model="contactno" :disabled="!$isEditing" />
            <x-input label="Academic Rank" wire:model="academic_rank" :disabled="!$isEditing" />

            <x-select.styled label="Campus" wire:model.live="branch_id" :disabled="!$isEditing" :options="$branches->map(fn($b) => ['label' => $b->name, 'value' => $b->id])->toArray()"
                select="label:label|value:value" />

            <x-select.styled label="Department" wire:model="department_id" :disabled="!$isEditing" :options="$departments->map(fn($d) => ['label' => $d->name, 'value' => $d->id])->toArray()"
                select="label:label|value:value" />

            <x-select.styled label="Sex" wire:model="sex" :disabled="!$isEditing" :options="['Male', 'Female']" />

            <x-input label="Birthday" type="date" wire:model="birthday" :disabled="!$isEditing" />

            <div class="md:col-span-2">
                <x-textarea label="Address" wire:model="address" :disabled="!$isEditing" />
            </div>
        </div>

        @if ($isEditing)
            <div class="mt-8 flex justify-end">
                <x-button wire:click="confirmSave" color="primary" text="Save Changes" icon="check" />
            </div>
        @endif
    </div>
</div>
