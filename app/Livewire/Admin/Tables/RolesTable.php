<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Role;
use App\Traits\CanManage;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Facades\Rule;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use TallStackUi\Traits\Interactions;

final class RolesTable extends PowerGridComponent
{
    use CanManage, Interactions, WithExport;

    public string $tableName = 'rolesTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'roles-list')
                ->striped()
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),

            PowerGrid::header()
                ->showSearchInput()
                ->showToggleColumns()
                ->showSoftDeletes(showMessage: true),

            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Role::query()
            ->with('permissions')
            ->when($this->softDeletes === 'withTrashed', fn ($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn ($query) => $query->onlyTrashed());
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('guard_name')
            // ->add('permissions_list', fn (Role $role) => $role->permissions->pluck('name')->implode(', '))
            ->add('deleted_at', fn (Role $role) => $role->deleted_at?->format('d/m/Y'));
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id'),
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Guard', 'guard_name')->sortable()->searchable(),
            // Column::make('Permissions', 'permissions_list'),
            Column::make('Deleted At', 'deleted_at')->sortable(),
            Column::action('Action'),
        ];
    }

    public function actions($row): array
    {
        $actions = [];

        if ($this->canManage('roles.update')) {
            $actions[] = Button::add('edit-role')
                ->slot('Edit')
                ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700 dark:group-hover:text-blue-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 dark:hover:bg-blue-800 dark:hover:text-blue-400 transition-all duration-300 cursor-pointer')
                ->dispatch('openEditModal', ['role' => $row->id]);
        }

        if ($this->canManage('roles.delete')) {
            $actions[] = Button::add('delete-role')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700 dark:group-hover:text-red-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 dark:hover:bg-red-800 dark:hover:text-red-400 transition-all duration-300 cursor-pointer')
                ->call('confirmDeleteRole', ['id' => $row->id]);
        }

        if ($this->canManage('roles.restore')) {
            $actions[] = Button::add('restore-role')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->call('confirmRestoreRole', ['id' => $row->id]);
        }

        return $actions;
    }

    public function actionRules($row): array
    {
        return [
            Rule::button('edit-role')->when(fn ($row) => $row->trashed())->hide(),
            Rule::button('delete-role')->when(fn ($row) => $row->trashed())->hide(),
            Rule::button('restore-role')->when(fn ($row) => ! $row->trashed())->hide(),
        ];
    }

    public function confirmDeleteRole(array $params): void
    {
        $this->ensureCanManage('roles.delete');

        $role = Role::findOrFail((int) $params['id']);

        $this->dialog()
            ->question('Warning!', 'Are you sure you want to delete '.e($role->name).'?')
            ->confirm('Yes, delete', 'deleteRole', $role->id)
            ->cancel('Cancel')
            ->send();
    }

    public function deleteRole(int $id): void
    {
        $this->ensureCanManage('roles.delete');

        try {
            Role::findOrFail($id)->delete();
            $this->toast()->success('Deleted', 'Role moved to trash.')->send();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
        } catch (Exception $e) {
            Log::error('Role Deletion Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to delete role. Please try again or contact support.')->send();
        }
    }

    public function confirmRestoreRole(array $params): void
    {
        $this->ensureCanManage('roles.restore');

        $role = Role::withTrashed()->findOrFail((int) $params['id']);

        $this->dialog()
            ->question('Restore?', 'Are you sure you want to restore '.e($role->name).'?')
            ->confirm('Yes, restore', 'restoreRole', $role->id)
            ->cancel('Cancel')
            ->send();
    }

    public function restoreRole(int $id): void
    {
        $this->ensureCanManage('roles.restore');

        try {
            Role::withTrashed()->findOrFail($id)->restore();
            $this->toast()->success('Restored', 'Role has been restored.')->send();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
        } catch (Exception $e) {
            Log::error('Role Restoration Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to restore role. Please try again or contact support.')->send();
        }
    }
}
