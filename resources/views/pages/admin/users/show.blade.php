<?php

use App\Livewire\Forms\Admin\UsersForm;
use App\Models\Campus;
use App\Models\College;
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
    public UsersForm $form;
    public bool $isEditing = false;

    public Collection $campuses;
    public Collection $colleges;
    public Collection $departments;
    public Collection $availableRoles;

    public function mount(User $user)
    {
        $this->user = $user->load(['facultyProfile', 'employeeProfile', 'roles']);
        $this->form->setValues($this->user);

        $this->campuses = Campus::where('is_active', true)->orderBy('name')->get();
        $this->availableRoles = Role::all();

        $this->colleges = $this->form->campus_id
            ? College::where('campus_id', $this->form->campus_id)->where('is_active', true)->orderBy('name')->get()
            : collect();
        $this->departments = $this->form->college_id
            ? Department::where('college_id', $this->form->college_id)->where('is_active', true)->orderBy('name')->get()
            : collect();
    }

    public function updatedFormCampusId($value)
    {
        $this->colleges = filled($value)
            ? College::where('campus_id', $value)->where('is_active', true)->orderBy('name')->get()
            : collect();
        $this->departments = collect();
        $this->form->college_id = null;
        $this->form->department_id = null;
    }

    public function updatedFormCollegeId($value)
    {
        $this->departments = filled($value)
            ? Department::where('college_id', $value)->where('is_active', true)->orderBy('name')->get()
            : collect();
        $this->form->department_id = null;
    }

    // Triggered when switching from Faculty/Employee to Standard to clear out data visually
    public function updatedFormType($value)
    {
        if ($value === 'standard') {
            $this->form->campus_id = null;
            $this->form->college_id = null;
            $this->form->department_id = null;
            $this->colleges = collect();
            $this->departments = collect();
        }
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
        // Add a warning if they are downgrading a user to "Standard"
        if ($this->form->type === 'standard' && ($this->user->facultyProfile || $this->user->employeeProfile)) {
            $this->dialog()->warning('Warning!', 'Switching to "Standard" will permanently delete their specific Faculty or Employee records. Continue?')->confirm('Yes, Save Changes', 'save')->send();
            return;
        }

        $this->dialog()->question('Save Changes?', 'Are you sure you want to update this user?')->confirm('Yes', 'save')->send();
    }

    public function save()
    {
        $this->form->update();
        $this->user->refresh()->load(['facultyProfile', 'employeeProfile', 'roles']);
        $this->isEditing = false;
        $this->toast()->success('Success', 'User profile updated successfully.')->send();
    }
}; ?>

<div class="py-8">
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
            <x-button wire:click="confirmEdit" sm :color="$isEditing ? 'red' : 'primary'" :text="$isEditing ? 'Cancel' : 'Edit Profile'" :icon="$isEditing ? 'x-mark' : 'pencil'" />
            <x-button tag="a" href="{{ route('admin.users') }}" sm outline text="Back" />
        </div>
    </div>

    <div class="bg-white p-8 rounded-lg shadow dark:bg-zinc-800">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-input label="First Name" wire:model="form.first_name" :disabled="!$isEditing" />
            <x-input label="Middle Name" wire:model="form.middle_name" :disabled="!$isEditing" />
            <x-input label="Last Name" wire:model="form.last_name" :disabled="!$isEditing" />

            <x-input label="Account Email (Primary)" wire:model="form.email" :disabled="!$isEditing" />

            <div>
                @if ($isEditing)
                    <x-select.styled label="Account Roles" wire:model="form.roles" multiple :options="$availableRoles
                        ->map(fn($r) => ['label' => Str::headline($r->name), 'value' => $r->name])
                        ->toArray()"
                        select="label:label|value:value" />
                @else
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Roles</p>
                    <div class="mt-1">
                        @foreach ($user->roles as $role)
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                {{ Str::headline($role->name) }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                @if ($isEditing)
                    <x-select.styled label="Profile Type" wire:model.live="form.type" :options="[
                        ['label' => 'Standard User', 'value' => 'standard'],
                        ['label' => 'Faculty', 'value' => 'faculty'],
                        ['label' => 'Employee', 'value' => 'employee'],
                    ]"
                        select="label:label|value:value" />
                @else
                    <x-input label="Profile Type" :value="Str::headline($form->type)" disabled />
                @endif
            </div>

            {{-- Only show these fields if they are NOT a Standard User --}}
            @if ($form->type !== 'standard')
                <div class="md:col-span-3 mt-4">
                    <h4
                        class="text-sm font-semibold text-gray-600 border-b pb-1 dark:text-zinc-300 dark:border-zinc-700">
                        Assignment & Profile Details</h4>
                </div>

                <x-select.styled label="Campus" wire:model.live="form.campus_id" :disabled="!$isEditing" :options="$campuses->map(fn($campus) => ['label' => $campus->name, 'value' => $campus->id])->toArray()"
                    select="label:label|value:value" />
                <x-select.styled label="College" wire:model.live="form.college_id" :disabled="!$isEditing" :options="$colleges->map(fn($college) => ['label' => $college->name, 'value' => $college->id])->toArray()"
                    select="label:label|value:value" />
                <x-select.styled :label="$form->type === 'employee' ? 'Department (Optional)' : 'Department'" :hint="$form->type === 'employee' ? 'Leave this blank if the employee is not assigned to a department.' : null" :placeholder="$form->type === 'employee' ? 'Select a department if applicable' : 'Select a department'"
                    wire:model="form.department_id" :disabled="!$isEditing" :options="$departments->map(fn($d) => ['label' => $d->name, 'value' => $d->id])->toArray()"
                    select="label:label|value:value" :required="$form->type === 'faculty'" />

                @if ($form->type === 'faculty')
                    <x-input label="Academic Rank" wire:model="form.academic_rank" :disabled="!$isEditing" />
                    <x-input label="Contact No" wire:model="form.contactno" :disabled="!$isEditing" />
                    <x-select.styled label="Sex" wire:model="form.sex" :disabled="!$isEditing" :options="['Male', 'Female']" />
                    <x-input label="Birthday" type="date" wire:model="form.birthday" :disabled="!$isEditing" />
                    <div class="md:col-span-3">
                        <x-textarea label="Address" wire:model="form.address" :disabled="!$isEditing" />
                    </div>
                @elseif($form->type === 'employee')
                    <x-input label="Position" wire:model="form.position" :disabled="!$isEditing" />
                @endif
            @endif
        </div>

        @if ($isEditing)
            <div class="mt-8 flex justify-end">
                <x-button wire:click="confirmSave" color="primary" text="Save Changes" />
            </div>
        @endif
    </div>
</div>
