<?php

use App\Imports\FacultyProfilesImport;
use App\Livewire\Forms\Admin\FacultyProfileForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
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

new class extends Component {
    use CanManage, Interactions, WithFileUploads;

    public FacultyProfileForm $form;

    public bool $createModal = false;

    public bool $importModal = false;

    public $importFile;

    public array $colleges = [];

    public array $departments = [];

    public string $scope = 'department';

    public ?int $campusId = null;

    public ?int $collegeId = null;

    public ?int $departmentId = null;

    public string $campusName = '-';

    public string $collegeCode = '-';

    public string $collegeName = '-';

    public string $departmentCode = '-';

    public string $departmentName = '-';

    #[Computed]
    public function campuses()
    {
        return Campus::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($c) => ['label' => $c->name, 'value' => $c->id])
            ->toArray();
    }

    public function mount()
    {
        $this->ensureCanManage('faculty_profiles.view');

        $this->scope = $this->facultyRouteContext();

        if ($this->scope === 'college') {
            $college = $this->currentCollege();

            $this->campusId = (int) $college->campus_id;
            $this->collegeId = (int) $college->id;
            $this->campusName = $college->campus?->name ?? '-';
            $this->collegeCode = $college->code ?? '-';
            $this->collegeName = $college->name;

            $this->departments = $this->departmentOptionsForCollege($this->collegeId);
            $this->departmentId = (int) ($this->departments[0]['value'] ?? 0) ?: null;
            $selectedDepartment = Department::query()
                ->where('college_id', $this->collegeId)
                ->find($this->departmentId);

            $this->departmentCode = $selectedDepartment?->code ?? '-';
            $this->departmentName = $selectedDepartment?->name ?? '-';
        } else {
            $department = $this->currentDepartment();

            $this->campusId = (int) $department->campus_id;
            $this->collegeId = (int) $department->college_id;
            $this->departmentId = (int) $department->id;
            $this->campusName = $department->campus?->name ?? '-';
            $this->collegeCode = $department->college?->code ?? '-';
            $this->collegeName = $department->college?->name ?? '-';
            $this->departmentCode = $department->code ?? '-';
            $this->departmentName = $department->name;

            $this->departments = [
                [
                    'label' => filled($this->departmentCode) && $this->departmentCode !== '-'
                        ? $this->departmentCode . ' - ' . $this->departmentName
                        : $this->departmentName,
                    'value' => $this->departmentId,
                ],
            ];
        }

        $this->setScopedFormDefaults();
    }

    public function create()
    {
        $this->ensureCanManage('faculty_profiles.create');

        $this->resetValidation();
        $this->form->resetForm();
        $this->setScopedFormDefaults();
        $this->createModal = true;
    }

    public function save()
    {
        $this->ensureCanManage('faculty_profiles.create');

        try {
            $this->setScopedFormDefaults();

            if ($this->scope === 'college') {
                $selectedDepartment = Department::query()->where('college_id', $this->collegeId)->findOrFail((int) $this->form->department_id);

                $this->form->campus_id = (int) $selectedDepartment->campus_id;
                $this->form->college_id = (int) $selectedDepartment->college_id;
                $this->form->department_id = (int) $selectedDepartment->id;
            } else {
                $this->form->campus_id = $this->campusId;
                $this->form->college_id = $this->collegeId;
                $this->form->department_id = $this->departmentId;
            }

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

                if (!$user->hasRole('faculty')) {
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

    public function updatedFormDepartmentId($value): void
    {
        if ($this->scope !== 'college') {
            return;
        }

        $departmentId = is_array($value) ? (int) ($value['value'] ?? 0) : (int) $value;

        $department = Department::query()->where('college_id', $this->collegeId)->find($departmentId);

        if (!$department) {
            return;
        }

        $this->departmentId = (int) $department->id;
        $this->departmentCode = $department->code ?? '-';
        $this->departmentName = $department->name;
    }

    #[Computed]
    public function stats(): array
    {
        $baseQuery = $this->scopedFacultyQuery();

        return [
            'total' => (clone $baseQuery)->count(),
            'ranked' => (clone $baseQuery)->whereNotNull('academic_rank')->where('academic_rank', '!=', '')->count(),
            'departments' => (clone $baseQuery)->distinct('department_id')->count('department_id'),
        ];
    }

    public function facultyRouteContext(): string
    {
        if (request()->routeIs('college-faculty-profiles.*')) {
            return 'college';
        }

        if (request()->routeIs('faculty-profiles.*')) {
            return 'department';
        }

        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile', 'facultyProfile']);

        if ($user?->canAccessCollegeFacultyProfiles() && !$user?->canAccessDepartmentFacultyProfiles()) {
            return 'college';
        }

        return 'department';
    }

    protected function setScopedFormDefaults(): void
    {
        $this->form->campus_id = $this->campusId;
        $this->form->college_id = $this->collegeId;

        if ($this->scope === 'department') {
            $this->form->department_id = $this->departmentId;

            return;
        }

        if (!filled($this->form->department_id) && filled($this->departmentId)) {
            $this->form->department_id = $this->departmentId;
        }
    }

    protected function currentCollege(): College
    {
        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile.college', 'employeeProfile.campus', 'facultyProfile.college', 'facultyProfile.campus']);

        abort_unless($user?->canAccessCollegeFacultyProfiles(), 403);

        $profile = $user?->assignedAcademicProfile();

        abort_unless(filled($profile?->college_id), 403);

        return College::query()->with('campus')->findOrFail((int) $profile->college_id);
    }

    protected function currentDepartment(): Department
    {
        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile.department', 'employeeProfile.college', 'employeeProfile.campus', 'facultyProfile.department', 'facultyProfile.college', 'facultyProfile.campus']);

        abort_unless($user?->canAccessDepartmentFacultyProfiles(), 403);

        $profile = $user?->assignedAcademicProfile();

        abort_unless(filled($profile?->department_id), 403);

        return Department::query()
            ->with(['campus', 'college'])
            ->findOrFail((int) $profile->department_id);
    }

    protected function departmentOptionsForCollege(int $collegeId): array
    {
        return Department::query()
            ->where('college_id', $collegeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(
                fn(Department $department) => [
                    'label' => filled($department->code) ? $department->code . ' - ' . $department->name : $department->name,
                    'value' => (int) $department->id,
                ],
            )
            ->values()
            ->toArray();
    }

    protected function scopedFacultyQuery(): Builder
    {
        $query = FacultyProfile::query();

        if ($this->scope === 'college' && filled($this->collegeId)) {
            return $query->where('college_id', $this->collegeId);
        }

        if ($this->scope === 'department' && filled($this->departmentId)) {
            return $query->where('department_id', $this->departmentId);
        }

        return $query->whereRaw('1 = 0');
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold dark:text-white">
                    {{ $scope === 'college' ? $collegeCode : $departmentCode }}
                </h1>
                <x-badge :text="$scope === 'college' ? 'College Scope' : 'Department Scope'" color="blue" round />
            </div>
            <p class="italic text-zinc-600 dark:text-zinc-200">
                @if ($scope === 'college')
                    Managing faculty profiles under {{ $collegeName }}, {{ $campusName }}.
                @else
                    Managing faculty profiles for {{ $departmentName }} under {{ $collegeName }}, {{ $campusName }}.
                @endif
            </p>
        </div>

    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Faculty</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
                </div>

                <div class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-icon icon="users" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">With Rank</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['ranked'] }}</p>
                </div>

                <div class="rounded-lg bg-violet-50 p-2 text-violet-600 dark:bg-violet-950/40 dark:text-violet-300">
                    <x-icon icon="academic-cap" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Departments</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['departments'] }}</p>
                </div>

                <div class="rounded-lg bg-cyan-50 p-2 text-cyan-600 dark:bg-cyan-950/40 dark:text-cyan-300">
                    <x-icon icon="building-office-2" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
    </div>

    <x-card>
        <div class="flex flex-col gap-4 border-b border-zinc-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Faculty List</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Search profiles, review assignments, and open a detailed record for edits.
                </p>
            </div>

            <div class="flex flex-col items-start gap-2 md:items-end">
                <div class="flex flex-wrap gap-2">
                    @can('faculty_profiles.create')
                        <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray"
                            text="Import Faculty" />
                        <x-button wire:click="create" sm color="primary" icon="plus" text="New Faculty" />
                    @endcan
                </div>
            </div>
        </div>

        <div class="p-6">
            <livewire:tables.admin.faculty-profiles-table :context="$scope" :college-id="$collegeId" :department-id="$departmentId" />
        </div>
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
                <x-input label="Campus" :value="$campusName" disabled />
                <x-input label="College" :value="filled($collegeCode) && $collegeCode !== '-' ? $collegeCode . ' - ' . $collegeName : $collegeName" disabled />

                @if ($scope === 'college')
                    <x-select.styled label="Department" wire:model="form.department_id" :options="$departments"
                        select="label:label|value:value" />
                @else
                    <x-input label="Department" :value="filled($departmentCode) && $departmentCode !== '-' ? $departmentCode . ' - ' . $departmentName : $departmentName" disabled />
                @endif
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
