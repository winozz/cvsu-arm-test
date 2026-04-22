<?php

use App\Imports\FacultyProfilesImport;
use App\Livewire\Forms\Admin\FacultyProfileForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use App\Traits\CanManage;
use App\Traits\HasCascadingLocationSelects;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use CanManage, HasCascadingLocationSelects, Interactions, WithFileUploads;

    public FacultyProfileForm $form;

    public bool $createModal = false;

    public bool $importModal = false;

    public $importFile;

    public array $colleges = [];

    public array $departments = [];

    #[Computed]
    public function campuses()
    {
        return Campus::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['label' => $c->name, 'value' => $c->id])
            ->toArray();
    }

    public function mount()
    {
        $this->ensureCanManage('faculty_profiles.view');
    }

    public function create()
    {
        $this->ensureCanManage('faculty_profiles.create');

        $this->resetValidation();
        $this->form->resetForm();
        $this->createModal = true;
    }

    public function save()
    {
        $this->ensureCanManage('faculty_profiles.create');

        try {
            $this->form->validateForm();
            $assignment = $this->form->resolveAcademicAssignment();

            DB::transaction(function () use ($assignment): void {
                $user = User::withTrashed()->firstOrNew([
                    'email' => $this->form->email,
                ]);

                $user->fill([
                    'name' => $this->form->fullName(),
                    'password' => $user->password ?: Hash::make('password123'),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'is_active' => $user->exists ? $user->is_active : true,
                ]);
                $user->save();

                if ($user->trashed()) {
                    $user->restore();
                }

                if (! $user->hasRole('faculty')) {
                    $user->assignRole('faculty');
                }

                $facultyProfile = FacultyProfile::withTrashed()->firstOrNew([
                    'user_id' => $user->id,
                ]);

                $facultyProfile->fill(
                    array_merge($assignment, [
                        'first_name' => $this->form->first_name,
                        'middle_name' => $this->form->middle_name,
                        'last_name' => $this->form->last_name,
                        'email' => $this->form->email,
                        'academic_rank' => $this->form->academic_rank,
                        'contactno' => $this->form->contactno,
                        'sex' => $this->form->sex,
                        'birthday' => $this->form->birthday ?: null,
                        'address' => $this->form->address,
                        'updated_by' => Auth::id(),
                    ]),
                );
                $facultyProfile->user_id = $user->id;
                $facultyProfile->save();

                if ($facultyProfile->trashed()) {
                    $facultyProfile->restore();
                }
            });

            $this->createModal = false;
            $this->form->resetForm();
            $this->toast()->success('Success', 'Faculty profile and user account created successfully.')->send();
            $this->dispatch('pg:eventRefresh-facultyProfilesTable');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Faculty profile creation failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->toast()->error('Error', 'Unable to create the faculty profile right now.')->send();
        }
    }

    public function import()
    {
        $this->ensureCanManage('faculty_profiles.create');

        try {
            $this->validate(['importFile' => 'required|mimes:csv,xlsx,xls']);
            Excel::import(new FacultyProfilesImport(Auth::id()), $this->importFile);

            $this->importModal = false;
            $this->importFile = null;
            $this->toast()->success('Success', 'Faculty imported successfully.')->send();
            $this->dispatch('pg:eventRefresh-facultyProfilesTable');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Faculty import failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->toast()->error('Error', 'Unable to import faculty right now.')->send();
        }
    }

    #[Computed]
    public function pageContext(): array
    {
        return [
            'campuses' => Campus::query()->count(),
            'colleges' => College::query()->count(),
            'departments' => Department::query()->count(),
        ];
    }

    #[Computed]
    public function stats(): array
    {
        $baseQuery = FacultyProfile::query();

        return [
            'total' => (clone $baseQuery)->count(),
            'ranked' => (clone $baseQuery)->whereNotNull('academic_rank')->where('academic_rank', '!=', '')->count(),
            'departments' => (clone $baseQuery)->distinct('department_id')->count('department_id'),
        ];
    }

    public function facultyRouteContext(): string
    {
        return request()->routeIs('college-faculty-profiles.*') ? 'college' : 'department';
    }
};
?>

<div class="space-y-6 py-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">Faculty Profiles</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Manage all faculty records from one full list, with actions controlled only by assigned permissions.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('faculty_profiles.create')
                <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray" text="Import Faculty" />
                <x-button wire:click="create" sm color="primary" icon="plus" text="New Faculty" />
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <h3 class="">Total Faculty</h3>
            <span class="font-bold text-2xl">{{ $this->stats['total'] }}</span>
        </x-card>
        <x-card>
            <h3 class="">With Rank</h3>
            <span class="font-bold text-2xl">{{ $this->stats['ranked'] }}</span>
        </x-card>
        <x-card>
            <h3 class="">Departments</h3>
            <span class="font-bold text-2xl">{{ $this->stats['departments'] }}</span>
        </x-card>
    </div>

    <x-card class="flex flex-col items-start justify-between gap-4 px-6 py-4 md:flex-row md:items-center">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Faculty List</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Search profiles, review assignments, and open a detailed record for edits.
            </p>
        </div>
        <span class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ $this->pageContext['campuses'] }} campuses, {{ $this->pageContext['colleges'] }} colleges, {{ $this->pageContext['departments'] }} departments
        </span>
    </x-card>

    <x-card>
        <livewire:tables.admin.faculty-profiles-table :context="$this->facultyRouteContext()" />
    </x-card>

    <x-modal wire="createModal" title="New Faculty" size="4xl">
        <div class="space-y-4">
            <div
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100">
                Saving this faculty profile also creates the linked user account automatically and assigns the
                `faculty` role.
            </div>

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
                    select="label:label|value:value" />
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
            <p class="text-xs text-zinc-500">Headers needed: first_name, middle_name, last_name, email,
                department_id, academic_rank, contactno, sex, birthday, address, password</p>
        </div>
        <x-slot:footer>
            @can('faculty_profiles.create')
                <x-button flat text="Cancel" wire:click="$set('importModal', false)" />
                <x-button color="primary" text="Upload & Import" wire:click="import" wire:loading.attr="disabled" />
            @endcan
        </x-slot:footer>
    </x-modal>
</div>
