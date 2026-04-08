<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Facades\Rule;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use TallStackUi\Traits\Interactions;

final class PermissionsTable extends PowerGridComponent
{
    use Interactions, WithExport;

    public string $tableName = 'permissionsTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'permissions-list')
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
        return Permission::query()
            ->when($this->softDeletes === 'withTrashed', fn ($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn ($query) => $query->onlyTrashed());
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('guard_name')
            ->add('deleted_at', fn ($model) => $model->deleted_at ? Carbon::parse($model->deleted_at)->format('d/m/Y H:i:s') : null);
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Guard name', 'guard_name')->sortable()->searchable(),
            Column::make('Deleted at', 'deleted_at')->sortable(),
            Column::action('Action'),
        ];
    }

    public function actions($row): array
    {
        return [
            Button::add('edit-permission')
                ->slot('Edit')
                ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700 dark:group-hover:text-blue-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 dark:hover:bg-blue-800 dark:hover:text-blue-400 transition-all duration-300 cursor-pointer')
                ->dispatch('editPermission', ['permission' => $row->id]),

            Button::add('delete-permission')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700 dark:group-hover:text-red-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 dark:hover:bg-red-800 dark:hover:text-red-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmDeletePermission', ['id' => $row->id]),

            Button::add('restore-permission')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmRestorePermission', ['id' => $row->id]),
        ];
    }

    public function actionRules($row): array
    {
        return [
            Rule::button('edit-permission')->when(fn ($row) => $row->trashed())->hide(),
            Rule::button('delete-permission')->when(fn ($row) => $row->trashed())->hide(),
            Rule::button('restore-permission')->when(fn ($row) => ! $row->trashed())->hide(),
        ];
    }

    #[On('confirmDeletePermission')]
    public function confirmDeletePermission(int $id): void
    {
        $this->dialog()
            ->question('Warning', 'Are you sure you want to delete this permission?')
            ->confirm('Yes, delete', 'deletePermission', $id)
            ->cancel('Cancel')
            ->send();
    }

    #[On('deletePermission')]
    public function deletePermission(int $id): void
    {
        try {
            Permission::findOrFail($id)->delete();
            $this->toast()->success('Deleted', 'Permission moved to trash.')->send();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
        } catch (\Exception $e) {
            Log::error('Permission Deletion Failed'.$e->getMessage());
            $this->toast()->error('Error', 'Failed to delete permission. Please try again or contact support.')->send();
        }
    }

    #[On('confirmRestorePermission')]
    public function confirmRestorePermission(int $id): void
    {
        $this->dialog()
            ->question('Restore?', 'Are you sure you want to restore this permission?')
            ->confirm('Yes, restore', 'restorePermission', $id)
            ->cancel('Cancel')
            ->send();
    }

    #[On('restorePermission')]
    public function restorePermission(int $id): void
    {
        try {
            Permission::withTrashed()->findOrFail($id)->restore();
            $this->toast()->success('Restored', 'Permissionhas been restored.')->send();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
        } catch (\Exception $e) {
            Log::error('Permission Restoration Failed'.$e->getMessage());
            $this->toast()->error('Error', 'Failed to restore permission. Please try again or contact support.')->send();
        }
    }
}
