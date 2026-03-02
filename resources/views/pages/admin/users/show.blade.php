<?php

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

    public User $user;

    public bool $isEditing = false;

    public string $profileType = '';

    // Form Fields
    public $first_name = '';

    public $last_name = '';

    public $branch_id = null;

    public $department_id = null;

    public $academic_rank = '';

    public $position = '';

    // Dropdown options
    public Collection $branches;

    public Collection $departments;

    public function mount(User $user)
    {
        $this->user = $user->load(['facultyProfile.department', 'facultyProfile.branch', 'employeeProfile.department', 'employeeProfile.branch', 'roles']);
        $this->branches = Branch::where('is_active', true)->get();
        $this->departments = collect();

        $profile = null;

        if ($this->user->facultyProfile) {
            $this->profileType = 'faculty';
            $profile = $this->user->facultyProfile;
            $this->academic_rank = $profile->academic_rank;
        } elseif ($this->user->employeeProfile) {
            $this->profileType = 'employeeProfile';
            $profile = $this->user->employeeProfile;
            $this->position = $profile->position;
        }

        if ($profile) {
            $this->first_name = $profile->first_name;
            $this->last_name = $profile->last_name;
            $this->branch_id = $profile->branch_id;
            $this->department_id = $profile->department_id;

            if ($this->branch_id) {
                $this->departments = Department::where('branch_id', $this->branch_id)->where('is_active', true)->get();
            }
        }
    }

    public function updatedBranchId($branchId)
    {
        $this->departments = Department::where('branch_id', $branchId)->where('is_active', true)->get();
        $this->department_id = null;
    }

    // --- STEP 1: Dialog to unlock editing ---
    public function confirmEdit()
    {
        if ($this->isEditing) {
            // If already editing, just cancel without dialog
            $this->isEditing = false;

            return;
        }

        $this->dialog()->question('Enable Editing?', 'Are you sure you want to modify this user\'s profile data?')
            ->confirm('Yes, allow edit', 'enableEditing')
            ->cancel('Cancel')
            ->send();
    }

    public function enableEditing()
    {
        $this->isEditing = true;
    }

    // --- STEP 2: Dialog to save changes ---
    public function confirmSave()
    {
        $this->dialog()->question('Confirm Changes', 'Are you sure you want to save these profile updates?')
            ->confirm('Yes, save', 'save')
            ->cancel('Cancel')
            ->send();
    }

    public function save()
    {
        $this->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
            'department_id' => 'required|exists:departments,id',
        ]);

        $fullName = trim($this->first_name.' '.$this->last_name);

        // Update main user record
        $this->user->update(['name' => $fullName]);

        // Update specific profile
        if ($this->profileType === 'faculty') {
            $this->user->facultyProfile->update([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'branch_id' => $this->branch_id,
                'department_id' => $this->department_id,
                'academic_rank' => $this->academic_rank,
            ]);
        } elseif ($this->profileType === 'employeeProfile') {
            $this->user->employeeProfile->update([
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'branch_id' => $this->branch_id,
                'department_id' => $this->department_id,
                'position' => $this->position,
            ]);
        }

        // Refresh relationships to update the view
        $this->user->refresh();
        if ($this->branch_id) {
            $this->departments = Department::where('branch_id', $this->branch_id)->where('is_active', true)->get();
        }

        $this->isEditing = false;
        $this->toast()->success('Success', 'Profile updated successfully.')->send();
    }
};
?>

<div class="max-w-5xl mx-auto py-8">
    <x-toast />
    <x-dialog />

    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div class="flex items-center gap-4">
            @if($user->avatar)
            <img class="w-16 h-16 rounded-full object-cover shadow-sm" src="{{ $user->avatar }}"
                alt="{{ $user->name }}">
            @else
            <div
                class="w-16 h-16 rounded-full bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-200 flex items-center justify-center text-xl font-bold shadow-sm">
                {{ strtoupper($user->initials()) }}
            </div>
            @endif
            <div>
                <h1 class="text-2xl font-bold dark:text-white">{{ $user->name }}</h1>
                <p class="text-gray-500">{{ $user->email }}</p>
                <div class="mt-1 flex flex-wrap gap-1">
                    @foreach($user->roles as $role)
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                        {{ Str::headline($role->name) }}
                    </span>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            <x-button wire:click="confirmEdit" sm :color="$isEditing ? 'red' : 'primary'"
                :icon="$isEditing ? 'x-mark' : 'pencil'" :text="$isEditing ? 'Cancel Edit' : 'Edit Profile'" />
            <x-button tag="a" href="{{ route('admin.users') }}" sm outline text="Back to Users" />
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
        <div class="mb-6 border-b border-gray-200 dark:border-gray-700 pb-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                {{ $profileType === 'faculty' ? 'Faculty Profile Details' : ($profileType === 'employeeProfile' ?
                'Employee
                Profile Details' : 'No Profile Set') }}
            </h3>
        </div>

        @if($profileType)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- First Name --}}
            <div>
                @if($isEditing)
                <x-input label="First Name" wire:model="first_name" />
                @else
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">First Name</p>
                <p class="text-gray-900 dark:text-white font-medium">{{ $first_name ?: 'Not specified' }}</p>
                @endif
            </div>

            {{-- Last Name --}}
            <div>
                @if($isEditing)
                <x-input label="Last Name" wire:model="last_name" />
                @else
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Name</p>
                <p class="text-gray-900 dark:text-white font-medium">{{ $last_name ?: 'Not specified' }}</p>
                @endif
            </div>

            {{-- Branch / Campus --}}
            <div>
                @if($isEditing)
                <x-select.styled label="Campus / Branch" wire:model.live="branch_id"
                    :options="$branches->map(fn($b) => ['label' => $b->name, 'value' => $b->id])->toArray()"
                    select="label:label|value:value" />
                @else
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Campus</p>
                <p class="text-gray-900 dark:text-white font-medium">
                    {{ $profileType === 'faculty' ? ($user->facultyProfile->branch->name ?? 'None') :
                    ($user->employeeProfile->branch->name ?? 'None') }}
                </p>
                @endif
            </div>

            {{-- Department --}}
            <div>
                @if($isEditing)
                <x-select.styled label="Department" wire:model="department_id"
                    :options="$departments->map(fn($d) => ['label' => $d->name, 'value' => $d->id])->toArray()"
                    select="label:label|value:value" />
                @else
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Department</p>
                <p class="text-gray-900 dark:text-white font-medium">
                    {{ $profileType === 'faculty' ? ($user->facultyProfile->department->name ?? 'None') :
                    ($user->employeeProfile->department->name ?? 'None') }}
                </p>
                @endif
            </div>

            {{-- Academic Rank / Position --}}
            <div class="md:col-span-2">
                @if($profileType === 'faculty')
                @if($isEditing)
                <x-input label="Academic Rank" wire:model="academic_rank" />
                @else
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Academic Rank</p>
                <p class="text-gray-900 dark:text-white font-medium">{{ $academic_rank ?: 'Not specified' }}</p>
                @endif
                @elseif($profileType === 'employeeProfile')
                @if($isEditing)
                <x-input label="Position" wire:model="position" />
                @else
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Position</p>
                <p class="text-gray-900 dark:text-white font-medium">{{ $position ?: 'Not specified' }}</p>
                @endif
                @endif
            </div>
        </div>

        @if($isEditing)
        <div class="flex justify-end pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
            <x-button wire:click="confirmSave" color="primary" text="Save All Changes" icon="check" />
        </div>
        @endif
        @else
        <div class="p-4 bg-yellow-50 text-yellow-700 rounded-lg">
            No specific profile (Faculty/Employee) has been configured for this user yet.
        </div>
        @endif
    </div>
</div>