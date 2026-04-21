<?php

use App\Livewire\Forms\Admin\FacultyProfileUpdateForm;
use App\Models\Campus;
use App\Models\FacultyProfile;
use App\Traits\CanManage;
use App\Traits\HasCascadingLocationSelects;
use App\Traits\HasManagedFacultyProfiles;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, HasCascadingLocationSelects, HasManagedFacultyProfiles, Interactions;

    public FacultyProfile $facultyProfile;

    public bool $isEditing = false;

    public FacultyProfileUpdateForm $form;

    public array $colleges = [];

    public array $departments = [];

    #[Computed]
    public function campuses()
    {
        return Campus::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($c) => ['label' => $c->name, 'value' => $c->id])
            ->toArray();
    }

    public function mount(FacultyProfile $facultyProfile)
    {
        $this->ensureCanManage('faculty_profiles.view');

        $this->facultyProfile = $this->findManagedFacultyProfile($facultyProfile->id)
            ->load(['user', 'campus', 'college', 'department']);
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

        try {
            $this->form->validateForm();
            $assignment = $this->form->resolveAcademicAssignment();

            DB::transaction(function () use ($assignment): void {
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
                    'updated_by' => Auth::id(),
                ]);

                if ($this->form->profile->user) {
                    $this->form->profile->user->update([
                        'name' => $this->form->fullName(),
                        'email' => $this->form->email,
                    ]);
                }
            });

            $this->facultyProfile = $this->findManagedFacultyProfile($this->facultyProfile->id)
                ->load(['user', 'campus', 'college', 'department']);
            $this->form->setValues($this->facultyProfile);
            $this->refreshAssignmentOptions();
            $this->isEditing = false;
            $this->toast()->success('Faculty Profile updated successfully.')->send();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Faculty profile update failed', [
                'faculty_profile_id' => $this->facultyProfile->id,
                'error' => $exception->getMessage(),
            ]);

            $this->toast()->error('Error', 'Unable to save the faculty profile right now.')->send();
        }
    }

    #[Computed]
    public function routeContext(): string
    {
        return request()->routeIs('college-faculty-profiles.*') ? 'college' : 'department';
    }

    public function facultyIndexRouteName(): string
    {
        return $this->routeContext() === 'college'
            ? 'college-faculty-profiles.index'
            : 'faculty-profiles.index';
    }

    public function assignmentPath(): string
    {
        return collect([
            $this->facultyProfile->campus?->name,
            $this->facultyProfile->college?->name,
            $this->facultyProfile->department?->name,
        ])->filter()->implode(' / ');
    }
}; ?>

<div class="space-y-6 py-8">
    <x-toast />
    <x-dialog />

    <nav class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
        <a href="{{ route($this->facultyIndexRouteName()) }}" class="hover:text-primary-600 dark:hover:text-primary-400">Faculty</a>
        <span>/</span>
        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $form->first_name }} {{ $form->last_name }}</span>
    </nav>

    <x-card class="flex flex-col items-start justify-between gap-4 p-6 md:flex-row md:items-center">
        <div class="flex items-start gap-4">
            <div
                class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-xl font-bold text-primary-700 dark:bg-zinc-700 dark:text-zinc-200">
                {{ strtoupper(substr($form->first_name, 0, 1) . substr($form->last_name, 0, 1)) }}
            </div>
            <div>
                <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ $form->first_name }} {{ $form->last_name }}</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $form->email }}</p>
                @if ($facultyProfile->academic_rank)
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $facultyProfile->academic_rank }}</p>
                @endif
            </div>
        </div>

        <div class="flex gap-2">
            @can('faculty_profiles.update')
                <x-button wire:click="confirmEdit" sm :color="$isEditing ? 'red' : 'primary'" :text="$isEditing ? 'Cancel' : 'Edit Profile'" :icon="$isEditing ? 'x-mark' : 'pencil'" />
            @endcan
            @can('faculty_profiles.view')
                <x-button tag="a" href="{{ route($this->facultyIndexRouteName()) }}" sm outline text="Back to List" />
            @endcan
        </div>
    </x-card>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Campus</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{ $facultyProfile->campus?->name ?? 'Not assigned' }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">College</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{ $facultyProfile->college?->name ?? 'Not assigned' }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Department</p>
            <p class="mt-1 text-lg font-semibold text-zinc-900 dark:text-white">{{ $facultyProfile->department?->name ?? 'Not assigned' }}</p>
        </x-card>
    </div>

    <x-card>
        <div class="space-y-6">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Faculty Details</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Basic identity, contact information, and linked account details.
                    </p>
                </div>

                @if ($isEditing)
                    <span class="text-sm text-amber-700 dark:text-amber-300">Editing enabled</span>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <x-input label="First Name" wire:model="form.first_name" :disabled="!$isEditing" />
                <x-input label="Middle Name" wire:model="form.middle_name" :disabled="!$isEditing" />
                <x-input label="Last Name" wire:model="form.last_name" :disabled="!$isEditing" />
                <x-input label="Email Address" wire:model="form.email" :disabled="!$isEditing" />
                <x-input label="Contact No" wire:model="form.contactno" :disabled="!$isEditing" />
                <x-input label="Academic Rank" wire:model="form.academic_rank" :disabled="!$isEditing" />
                <x-select.styled label="Sex" wire:model="form.sex" :disabled="!$isEditing" :options="['Male', 'Female']" />
                <x-input label="Birthday" type="date" wire:model="form.birthday" :disabled="!$isEditing" />

                <div class="md:col-span-2">
                    <x-textarea label="Address" wire:model="form.address" :disabled="!$isEditing" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 border-t border-zinc-200 pt-6 dark:border-zinc-700 md:grid-cols-2">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Linked Account</p>
                    <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                        {{ $facultyProfile->user?->name ?? 'No linked user account' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Account Status</p>
                    <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                        {{ $facultyProfile->user?->is_active ? 'Active' : 'No active account found' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Created</p>
                    <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">{{ $facultyProfile->created_at?->format('M d, Y h:i A') }}</p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Last Updated</p>
                    <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">{{ $facultyProfile->updated_at?->format('M d, Y h:i A') }}</p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Assignment</p>
                    <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">{{ $this->assignmentPath() }}</p>
                </div>
            </div>

            @if ($isEditing)
                <div class="rounded-lg border border-zinc-200 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                    Campus, college, and department are locked during edits to protect the assigned academic scope of
                    this faculty profile.
                </div>

                <div class="flex justify-end">
                    @can('faculty_profiles.update')
                        <x-button wire:click="confirmSave" color="primary" text="Save Changes" icon="check" />
                    @endcan
                </div>
            @endif
        </div>
    </x-card>
</div>
