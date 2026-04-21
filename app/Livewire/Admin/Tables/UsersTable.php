<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Role;
use App\Models\User;
use App\Traits\CanManage;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Responsive;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use TallStackUi\Traits\Interactions;

final class UsersTable extends PowerGridComponent
{
    use CanManage;
    use Interactions;
    use WithExport;

    public string $tableName = 'usersTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'user-accounts')
                ->striped()
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()
                ->showSearchInput()
                ->showSoftDeletes(showMessage: true),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
            PowerGrid::responsive()
                ->fixedColumns('name', Responsive::ACTIONS_COLUMN_NAME),
        ];
    }

    public function datasource(): Builder
    {
        return User::query()
            ->with([
                'roles',
                'facultyProfile.campus',
                'facultyProfile.college',
                'facultyProfile.department',
                'employeeProfile.campus',
                'employeeProfile.college',
                'employeeProfile.department',
            ])
            ->when($this->softDeletes === 'withTrashed', fn (Builder $query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn (Builder $query) => $query->onlyTrashed());
    }

    public function relationSearch(): array
    {
        return [
            'roles' => ['name'],
            'facultyProfile' => ['first_name', 'middle_name', 'last_name', 'academic_rank', 'email'],
            'employeeProfile' => ['first_name', 'middle_name', 'last_name', 'position'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('avatar_view', fn (User $user) => $this->avatarView($user))
            ->add('name')
            ->add('email')
            ->add('roles_list', fn (User $user) => $this->rolesList($user))
            ->add('profile_type', fn (User $user) => $this->profileTypeBadge($user))
            ->add('assignment_path', fn (User $user) => $this->assignmentPath($user))
            ->add('status_badge', fn (User $user) => $this->statusBadge((bool) $user->is_active));
    }

    public function columns(): array
    {
        return [
            Column::make('Avatar', 'avatar_view'),
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Email', 'email')->sortable()->searchable(),
            Column::make('Roles', 'roles_list'),
            Column::make('Profile', 'profile_type'),
            Column::make('Assignment', 'assignment_path'),
            Column::make('Status', 'status_badge', 'is_active')->sortable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('roles_list')
                ->dataSource(
                    Role::query()
                        ->orderBy('name')
                        ->get()
                        ->map(fn (Role $role) => [
                            'id' => $role->name,
                            'name' => Str::headline($role->name),
                        ])
                        ->toArray()
                )
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(function (Builder $query, $value) {
                    if (! filled($value)) {
                        return $query;
                    }

                    return $query->whereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', $value));
                }),
            Filter::select('profile_type')
                ->dataSource([
                    ['id' => 'faculty', 'name' => 'Faculty'],
                    ['id' => 'employee', 'name' => 'Employee'],
                    ['id' => 'dual', 'name' => 'Faculty + Employee'],
                    ['id' => 'standard', 'name' => 'Standard'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(function (Builder $query, $value) {
                    if (! filled($value)) {
                        return $query;
                    }

                    return match ($value) {
                        'faculty' => $query
                            ->whereHas('facultyProfile')
                            ->whereDoesntHave('employeeProfile'),
                        'employee' => $query
                            ->whereHas('employeeProfile')
                            ->whereDoesntHave('facultyProfile'),
                        'dual' => $query
                            ->whereHas('facultyProfile')
                            ->whereHas('employeeProfile'),
                        'standard' => $query
                            ->whereDoesntHave('facultyProfile')
                            ->whereDoesntHave('employeeProfile'),
                        default => $query,
                    };
                }),
            Filter::select('is_active')
                ->dataSource([
                    ['id' => '1', 'name' => 'Active'],
                    ['id' => '0', 'name' => 'Inactive'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(
                    fn (Builder $query, $value) => filled($value)
                        ? $query->where('is_active', (int) $value)
                        : $query
                ),
        ];
    }

    public function actions(User $row): array
    {
        $actions = [];

        if (! $row->trashed()) {
            if ($this->canManage('users.view')) {
                $actions[] = Button::add('manage')
                    ->slot('Manage')
                    ->icon('default-eye', ['class' => 'w-4 h-4 text-primary-500 group-hover:text-primary-700'])
                    ->class('group flex items-center gap-1 rounded border border-primary-500 px-2 py-1 text-xs font-bold text-primary-500 transition-all duration-300 hover:bg-zinc-100 hover:text-primary-700')
                    ->route('admin.users.show', ['user' => $row->id]);
            }

            if ($this->canManage('users.delete') && Auth::id() !== $row->id) {
                $actions[] = Button::add('delete')
                    ->slot('Delete')
                    ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700'])
                    ->class('group flex items-center gap-1 rounded border border-red-500 px-2 py-1 text-xs font-bold text-red-500 transition-all duration-300 hover:bg-zinc-100 hover:text-red-700')
                    ->call('confirmDelete', ['id' => $row->id]);
            }
        } elseif ($this->canManage('users.restore')) {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700'])
                ->class('group flex items-center gap-1 rounded border border-amber-500 px-2 py-1 text-xs font-bold text-amber-500 transition-all duration-300 hover:bg-zinc-100 hover:text-amber-700')
                ->call('confirmRestore', ['id' => $row->id]);
        }

        return $actions;
    }

    public function confirmDelete(array $params): void
    {
        $this->ensureCanManage('users.delete');

        $user = User::findOrFail((int) $params['id']);

        if (Auth::id() === $user->id) {
            $this->toast()->warning('Unavailable', 'You cannot delete your own account from this table.')->send();

            return;
        }

        $this->dialog()
            ->question('Delete User?', 'Move '.e($user->name).' to trash?')
            ->confirm('Delete', 'deleteUser', $user->id)
            ->cancel('Cancel')
            ->send();
    }

    public function deleteUser(int $id): void
    {
        $this->ensureCanManage('users.delete');

        if (Auth::id() === $id) {
            $this->toast()->warning('Unavailable', 'You cannot delete your own account from this table.')->send();

            return;
        }

        try {
            User::findOrFail($id)->delete();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Deleted', 'User moved to trash.')->send();
        } catch (Exception $exception) {
            Log::error('User deletion failed', [
                'user_id' => $id,
                'error' => $exception->getMessage(),
            ]);
            $this->toast()->error('Error', 'Failed to delete user. Please try again.')->send();
        }
    }

    public function confirmRestore(array $params): void
    {
        $this->ensureCanManage('users.restore');

        $user = User::withTrashed()->findOrFail((int) $params['id']);

        $this->dialog()
            ->question('Restore User?', 'Restore '.e($user->name).' from trash?')
            ->confirm('Restore', 'restoreUser', $user->id)
            ->cancel('Cancel')
            ->send();
    }

    public function restoreUser(int $id): void
    {
        $this->ensureCanManage('users.restore');

        try {
            User::withTrashed()->findOrFail($id)->restore();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Restored', 'User restored successfully.')->send();
        } catch (Exception $exception) {
            Log::error('User restore failed', [
                'user_id' => $id,
                'error' => $exception->getMessage(),
            ]);
            $this->toast()->error('Error', 'Failed to restore user. Please try again.')->send();
        }
    }

    protected function avatarView(User $user): string
    {
        if (filled($user->avatar)) {
            return '<img class="h-8 w-8 rounded-full object-cover" src="'.e($user->avatar).'" alt="'.e($user->name).'">';
        }

        return '<div class="flex h-8 w-8 items-center justify-center rounded-full bg-zinc-200 text-xs font-bold text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100">'
            .e(strtoupper($user->initials()))
            .'</div>';
    }

    protected function rolesList(User $user): string
    {
        if ($user->roles->isEmpty()) {
            return '<span class="text-sm italic text-zinc-400">No roles</span>';
        }

        return $user->roles
            ->map(fn ($role) => $this->badge(Str::headline((string) $role->name), 'border border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-800 dark:bg-blue-900/50 dark:text-blue-200'))
            ->implode(' ');
    }

    protected function profileTypeBadge(User $user): string
    {
        $label = match (true) {
            $user->facultyProfile !== null && $user->employeeProfile !== null => 'Faculty + Employee',
            $user->facultyProfile !== null => 'Faculty',
            $user->employeeProfile !== null => 'Employee',
            default => 'Standard',
        };

        $classes = match ($label) {
            'Faculty' => 'border border-indigo-200 bg-indigo-100 text-indigo-800 dark:border-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200',
            'Employee' => 'border border-teal-200 bg-teal-100 text-teal-800 dark:border-teal-800 dark:bg-teal-900/50 dark:text-teal-200',
            'Faculty + Employee' => 'border border-violet-200 bg-violet-100 text-violet-800 dark:border-violet-800 dark:bg-violet-900/50 dark:text-violet-200',
            default => 'border border-zinc-200 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200',
        };

        return $this->badge($label, $classes);
    }

    protected function assignmentPath(User $user): string
    {
        $profile = $user->facultyProfile ?? $user->employeeProfile;

        if (! $profile) {
            return '<span class="text-sm italic text-zinc-400">Not assigned</span>';
        }

        $path = collect([
            $profile->campus?->name,
            $profile->college?->name,
            $profile->department?->name,
        ])->filter()->implode(' / ');

        if ($path === '') {
            return '<span class="text-sm italic text-zinc-400">Not assigned</span>';
        }

        return '<span class="text-sm text-zinc-700 dark:text-zinc-100">'.e($path).'</span>';
    }

    protected function statusBadge(bool $isActive): string
    {
        return $this->badge(
            $isActive ? 'Active' : 'Inactive',
            $isActive
                ? 'border border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200'
                : 'border border-red-200 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-900/50 dark:text-red-200'
        );
    }

    protected function badge(string $label, string $classes): string
    {
        return '<span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium '.$classes.'">'
            .e($label)
            .'</span>';
    }
}
