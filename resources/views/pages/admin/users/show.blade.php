<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use Interactions;

    public User $user;
    public bool $isEditing = false;
    public string $profileType = '';

    // Form fields
    public $first_name = '';
    public $middle_name = '';
    public $last_name = '';
    public $email = '';
    public $branch_id = null;
    public $department_id = null;
    public $academic_rank = '';
    public $position = '';
    public $contactno = '';
    public $address = '';
    public $sex = '';
    public $birthday = '';

    // Roles property
    public array $selectedRoles = [];

    public Collection $branches;
    public Collection $departments;
    public Collection $availableRoles;

    public function mount(User $user)
    {
        $this->user = $user->load(['facultyProfile', 'employeeProfile', 'roles']);
        $this->branches = Branch::where('is_active', true)->get();
        $this->availableRoles = Role::all();
        $this->departments = collect();

        // Bind main user properties
        $this->email = $this->user->email;
        $this->selectedRoles = $this->user->roles->pluck('name')->toArray();

        $profile = $this->user->facultyProfile ?: $this->user->employeeProfile;
        $this->profileType = $this->user->facultyProfile ? 'faculty' : ($this->user->employeeProfile ? 'employee' : '');

        if ($profile) {
            $this->first_name = $profile->first_name;
            $this->middle_name = $profile->middle_name;
            $this->last_name = $profile->last_name;
            $this->branch_id = $profile->branch_id;
            $this->department_id = $profile->department_id;

            if ($this->profileType === 'faculty') {
                $this->academic_rank = $profile->academic_rank;
                $this->contactno = $profile->contactno;
                $this->address = $profile->address;
                $this->sex = $profile->sex;
                $this->birthday = $profile->birthday;
            } else {
                $this->position = $profile->position;
            }

            if ($this->branch_id) {
                $this->departments = Department::where('branch_id', $this->branch_id)->get();
            }
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
        $this->dialog()->question('Enable Editing?', 'Do you want to modify this user?')->confirm('Yes', 'enableEditing')->send();
    }

    public function enableEditing()
    {
        $this->isEditing = true;
    }

    public function confirmSave()
    {
        $this->dialog()->question('Save Changes?', 'This will update the user and profile record.')->confirm('Yes', 'save')->send();
    }

    public function save()
    {
        $this->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users,email,' . $this->user->id,
            'branch_id' => 'required',
            'department_id' => 'required',
            'selectedRoles' => 'required|array|min:1',
            'selectedRoles.*' => 'exists:roles,name',
        ]);

        $fullName = trim($this->first_name . ' ' . ($this->middle_name ? $this->middle_name . ' ' : '') . $this->last_name);

        // 1. Update main User table
        $this->user->update([
            'name' => $fullName,
            'email' => $this->email,
        ]);

        // 2. Sync Roles
        $this->user->syncRoles($this->selectedRoles);

        $commonData = [
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
        ];

        // 3. Update Profile Specifics
        if ($this->profileType === 'faculty') {
            $this->user->facultyProfile->update(
                array_merge($commonData, [
                    'email' => $this->email, // Keep email in sync for faculty table
                    'academic_rank' => $this->academic_rank,
                    'contactno' => $this->contactno,
                    'address' => $this->address,
                    'sex' => $this->sex,
                    'birthday' => $this->birthday,
                ]),
            );
        } elseif ($this->profileType === 'employee') {
            $this->user->employeeProfile->update(
                array_merge($commonData, [
                    'position' => $this->position,
                ]),
            );
        }

        // Refresh the user model to ensure the UI updates the roles immediately
        $this->user->load('roles');

        $this->isEditing = false;
        $this->toast()->success('User and Profile updated successfully.')->send();
    }
}; ?>

<div class="max-w-5xl mx-auto py-8">
    <div class="mb-6 flex justify-between items-center bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <div class="flex items-center gap-4">
            <div
                class="w-16 h-16 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xl font-bold dark:bg-zinc-700 dark:text-zinc-200">
                {{ strtoupper($user->initials()) }}
            </div>
            <div>
                <h1 class="text-2xl font-bold dark:text-white">{{ $user->name }}</h1>
                <p class="text-zinc-500 dark:text-zinc-400">{{ $user->email }}</p>
                <div class="mt-1 flex flex-wrap gap-1">
                    {{-- Displays current roles regardless of edit state --}}
                    @foreach ($user->roles as $role)
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                            {{ Str::headline($role->name) }}
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="flex gap-2">
            <x-button wire:click="confirmEdit" sm :color="$isEditing ? 'red' : 'primary'" :text="$isEditing ? 'Cancel' : 'Edit Profile'" />
            <x-button tag="a" href="{{ route('admin.users') }}" sm outline text="Back" />
        </div>
    </div>

    <div class="bg-white p-8 rounded-lg shadow dark:bg-zinc-800">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-input label="First Name" wire:model="first_name" :disabled="!$isEditing" />
            <x-input label="Middle Name" wire:model="middle_name" :disabled="!$isEditing" />
            <x-input label="Last Name" wire:model="last_name" :disabled="!$isEditing" />

            <x-input label="Account Email (Primary)" wire:model="email" :disabled="!$isEditing" />

            {{-- Roles Field --}}
            <div>
                @if ($isEditing)
                    <x-select.styled label="Account Roles" wire:model="selectedRoles" multiple :options="$availableRoles
                        ->map(fn($r) => ['label' => Str::headline($r->name), 'value' => $r->name])
                        ->toArray()"
                        select="label:label|value:value" />
                @else
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Roles</p>

                    <div class="mt-1">
                        {{-- Displays current roles regardless of edit state --}}
                        @foreach ($user->roles as $role)
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                {{ Str::headline($role->name) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <x-select.styled label="Campus" wire:model.live="branch_id" :disabled="!$isEditing" :options="$branches->map(fn($b) => ['label' => $b->name, 'value' => $b->id])->toArray()"
                select="label:label|value:value" />

            <x-select.styled label="Department" wire:model="department_id" :disabled="!$isEditing" :options="$departments->map(fn($d) => ['label' => $d->name, 'value' => $d->id])->toArray()"
                select="label:label|value:value" />

            @if ($profileType === 'faculty')
                <x-input label="Academic Rank" wire:model="academic_rank" :disabled="!$isEditing" />
                <x-input label="Contact No" wire:model="contactno" :disabled="!$isEditing" />
                <x-select.styled label="Sex" wire:model="sex" :disabled="!$isEditing" :options="['Male', 'Female']" />
                <x-input label="Birthday" type="date" wire:model="birthday" :disabled="!$isEditing" />
                <div class="md:col-span-3">
                    <x-textarea label="Address" wire:model="address" :disabled="!$isEditing" />
                </div>
            @elseif($profileType === 'employee')
                <x-input label="Position" wire:model="position" :disabled="!$isEditing" />
            @endif
        </div>

        @if ($isEditing)
            <div class="mt-8 flex justify-end">
                <x-button wire:click="confirmSave" color="primary" text="Save Changes" />
            </div>
        @endif
    </div>
</div>
