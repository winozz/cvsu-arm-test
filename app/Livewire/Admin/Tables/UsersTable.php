<?php

namespace App\Livewire\Admin\Tables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
use Spatie\Permission\Models\Role;
use TallStackUi\Traits\Interactions;

final class UsersTable extends PowerGridComponent
{
    use Interactions, WithExport;

    public string $tableName = 'usersTable';

    /**
     * Override the bood method of PowerGridComponent
     */
    public function boot(): void
    {
        // Place filters outside the table header
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            // Set up export options
            PowerGrid::exportable(fileName: 'users-list')
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
            ->role(['superAdmin', 'collegeAdmin', 'deptAdmin', 'faculty'])
            ->with(['facultyProfile', 'employeeProfile', 'roles'])
            ->when($this->softDeletes === 'withTrashed', fn ($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn ($query) => $query->onlyTrashed());
    }

    public function relationSearch(): array
    {
        return [
            'employeeProfile' => ['position'],
            'facultyProfile' => ['academic_rank'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('avatar_view', function ($item) {
                if (! empty($item->avatar)) {
                    return '<img class="w-8 h-8 shrink-0 grow-0 rounded-full object-cover" src="'.$item->avatar.'" alt="'.$item->name.'">';
                }

                return '<div class="w-8 h-8 shrink-0 grow-0 rounded-full bg-zinc-200 dark:bg-zinc-400 text-zinc-800 flex items-center justify-center text-xs font-bold" title="'.$item->name.'">'
                    .strtoupper($item->initials()).
                    '</div>';
            })
            ->add('name')
            ->add('email')
            ->add('roles_list', function (User $user) {
                return $user->roles->map(function ($role) {
                    $formattedName = Str::headline($role->name);

                    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300 border border-blue-200 dark:border-blue-800">'
                        .$formattedName.
                        '</span>';
                })->implode(' ');
            })
            ->add('position_rank', function (User $user) {
                if ($user->employeeProfile && $user->employeeProfile->position) {
                    return $user->employeeProfile->position;
                }
                if ($user->facultyProfile && $user->facultyProfile->academic_rank) {
                    return $user->facultyProfile->academic_rank;
                }

                return '<span class="text-zinc-400 italic">Not set</span>';
            });
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('Avatar', 'avatar_view'),
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Email', 'email')->sortable()->searchable(),
            Column::make('Role', 'roles_list'),
            Column::make('Position / Rank', 'position_rank'),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('roles_list')
                ->dataSource(
                    Role::query()
                        ->whereIn('name', ['superAdmin', 'collegeAdmin', 'deptAdmin', 'faculty'])
                        ->get()
                        ->map(fn ($role) => [
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

                    return $query->whereHas('roles', function (Builder $q) use ($value) {
                        $q->where('name', $value);
                    });
                }),

            Filter::inputText('position_rank')->builder(function (Builder $query, $value) {
                $searchTerm = is_array($value) ? ($value['value'] ?? '') : $value;
                if (empty($searchTerm)) {
                    return $query;
                }

                return $query->where(function ($subQuery) use ($searchTerm) {
                    $subQuery->whereHas('employeeProfile', function ($q) use ($searchTerm) {
                        $q->where('position', 'like', "%{$searchTerm}%");
                    })->orWhereHas('facultyProfile', function ($q) use ($searchTerm) {
                        $q->where('academic_rank', 'like', "%{$searchTerm}%");
                    });
                });
            }),
        ];
    }

    public function actions(User $row): array
    {
        $actions = [];

        if (! $row->trashed()) {
            $actions[] = Button::add('view')
                ->slot('View')
                ->icon('default-eye', ['class' => 'w-4 h-4 text-primary-500 group-hover:text-primary-700'])
                ->class('group flex items-center gap-1 font-bold text-xs text-primary-500 rounded border border-primary-500 px-2 py-1 hover:text-primary-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->route('admin.users.show', ['user' => $row->id]);

            $actions[] = Button::add('delete')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700'])
                ->class('group flex items-center gap-1 font-bold text-xs text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmDelete', ['id' => $row->id]);
        } else {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmRestore', ['id' => $row->id]);
        }

        return $actions;
    }
}
