<?php

use App\Imports\UsersImport;
use App\Livewire\Forms\Admin\UserForm;
use App\Models\Campus;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Traits\CanManage;
use App\Traits\HasCascadingLocationSelects;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, HasCascadingLocationSelects, Interactions, WithFileUploads;

    public UserForm $form;

    public bool $createModal = false;

    public bool $importModal = false;

    public mixed $importFile = null;

    public array $colleges = [];

    public array $departments = [];

    public function mount(): void
    {
        $this->ensureCanManage('users.view');
        $this->form->resetForm();
    }

    #[Computed]
    public function campuses(): array
    {
        return Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn(Campus $campus) => ['label' => $campus->name, 'value' => $campus->id])
            ->toArray();
    }

    #[Computed]
    public function roleSelectOptions(): array
    {
        return Role::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn(Role $role) => ['label' => $role->display_name, 'value' => $role->name])
            ->toArray();
    }

    #[Computed]
    public function permissionSelectOptions(): array
    {
        return Permission::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn(Permission $permission) => ['label' => $permission->display_name, 'value' => $permission->name])
            ->toArray();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return [['label' => 'Standard', 'value' => 'standard'], ['label' => 'Faculty', 'value' => 'faculty'], ['label' => 'Employee', 'value' => 'employee'], ['label' => 'Faculty + Employee', 'value' => 'dual']];
    }

    public function updatedFormType(string $value): void
    {
        if ($value !== 'standard') {
            return;
        }

        $this->form->clearAssignment();
        $this->colleges = [];
        $this->departments = [];
    }

    public function openCreateModal(): void
    {
        $this->ensureCanManage('users.create');

        $this->resetValidation();
        $this->form->resetForm();
        $this->colleges = [];
        $this->departments = [];
        $this->createModal = true;
    }

    public function reopenCreateModal(): void
    {
        $this->createModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->createModal = false;
        $this->resetValidation();
        $this->form->resetForm();
        $this->colleges = [];
        $this->departments = [];
    }

    public function save(): void
    {
        $this->ensureCanManage('users.create');

        try {
            $this->form->validateForm();

            DB::transaction(function (): void {
                $user = User::withTrashed()->firstOrNew([
                    'email' => $this->form->email,
                ]);

                $user->fill(
                    array_merge($this->form->userAttributes(), [
                        'password' => $user->password,
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ]),
                );
                $user->save();

                if ($user->trashed()) {
                    $user->restore();
                }

                $user->syncRoles($this->form->normalizedRoles());
                $user->syncPermissions($this->form->resolvedDirectPermissions());

                $this->syncProfiles($user);
            });

            $this->closeCreateModal();
            $this->dispatch('pg:eventRefresh-usersTable');
            $this->toast()->success('Success', 'User created successfully.')->send();
        } catch (ValidationException $exception) {
            $this->reopenCreateModal();
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('User creation failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->reopenCreateModal();
            $this->toast()->error('Error', 'Unable to create the user right now.')->send();
        }
    }

    public function confirmSave(): void
    {
        $this->ensureCanManage('users.create');

        $this->form->validateForm();

        $this->createModal = false;

        $this->dialog()->question('Create user?', 'Are you sure you want to create this user account?')->confirm('Yes, create user', 'save')->cancel('Cancel', 'reopenCreateModal')->send();
    }

    public function import(): void
    {
        $this->ensureCanManage('users.create');

        $this->validate([
            'importFile' => ['required', 'file', 'mimes:csv,xlsx,xls'],
        ]);

        try {
            Excel::import(new UsersImport(), $this->importFile);

            $this->importModal = false;
            $this->importFile = null;
            $this->dispatch('pg:eventRefresh-usersTable');
            $this->toast()->success('Imported', 'Users imported successfully.')->send();
        } catch (Throwable $exception) {
            Log::error('User import failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->toast()->error('Error', 'Unable to import the file right now.')->send();
        }
    }

    protected function syncProfiles(User $user): void
    {
        $assignment = $this->form->resolveAcademicAssignment();

        if ($this->form->requiresFacultyProfile()) {
            $profile = FacultyProfile::withTrashed()->firstOrNew(['user_id' => $user->id]);
            $profile->fill($this->form->facultyProfileAttributes($assignment));
            $profile->user_id = $user->id;
            $profile->updated_by = Auth::id();
            $profile->save();

            if ($profile->trashed()) {
                $profile->restore();
            }
        } else {
            FacultyProfile::query()->where('user_id', $user->id)->delete();
        }

        if ($this->form->requiresEmployeeProfile()) {
            $profile = EmployeeProfile::withTrashed()->firstOrNew(['user_id' => $user->id]);
            $profile->fill($this->form->employeeProfileAttributes($assignment));
            $profile->user_id = $user->id;
            $profile->save();

            if ($profile->trashed()) {
                $profile->restore();
            }
        } else {
            EmployeeProfile::query()->where('user_id', $user->id)->delete();
        }
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">User Management</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Manage account access, profile types, and academic assignments from one place.
            </p>
        </div>

        @can('users.create')
            <div class="flex gap-2">
                <x-button color="slate" text="Import" icon="arrow-up-tray" wire:click="$set('importModal', true)" sm
                    outline />
                <x-button color="primary" text="New User" icon="plus" wire:click="openCreateModal" sm />
            </div>
        @endcan
    </div>

    <x-alert color="yellow" xs icon="exclamation-triangle" title="Google sign-in only"
        text="Users created here authenticate with Google. Local password login is currently disabled." />

    <x-card>
        <livewire:tables.admin.users-table />
    </x-card>

    <x-modal wire="createModal" title="Create User" size="4xl">
        @include('pages.admin.users.partials.form-fields')

        <x-slot:footer>
            @can('users.create')
                <x-button flat text="Cancel" wire:click="closeCreateModal" sm />
                <x-button color="primary" text="Create User" wire:click="confirmSave" sm />
            @endcan
        </x-slot:footer>
    </x-modal>

    <x-modal wire="importModal" title="Import Users">
        <div class="space-y-4">
            <x-upload wire:model="importFile" label="Select Excel/CSV File" hint="Supported files: .xlsx, .csv" />

            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                Recommended headers: first_name, middle_name, last_name, email, type, roles, direct_permissions,
                is_active, campus_id, college_id, department_id, academic_rank, position, contactno, sex, birthday,
                address, password
            </p>
        </div>

        <x-slot:footer>
            @can('users.create')
                <x-button flat text="Cancel" wire:click="$set('importModal', false)" sm />
                <x-button color="green" text="Import Users" wire:click="import" sm />
            @endcan
        </x-slot:footer>
    </x-modal>
</div>
