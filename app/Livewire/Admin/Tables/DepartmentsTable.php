<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Department;
use App\Traits\CanManage;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Facades\Rule;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use TallStackUi\Traits\Interactions;

final class DepartmentsTable extends PowerGridComponent
{
    use CanManage, Interactions, WithExport;

    public int $collegeId;

    public string $tableName = 'departmentsTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'departments-list')
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
        return Department::query()
            ->where('college_id', $this->collegeId)
            ->when($this->softDeletes === 'withTrashed', fn ($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn ($query) => $query->onlyTrashed());
    }

    public function relationSearch(): array
    {
        return [];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('code')
            ->add('description');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),

            Column::make('Description', 'description')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::datetimepicker('created_at'),
        ];
    }

    public function actions(Department $row): array
    {
        $actions = [];

        if ($this->canManage('departments.update')) {
            $actions[] = Button::add('edit')
                ->slot('Edit')
                ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700 dark:group-hover:text-blue-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 dark:hover:bg-blue-800 dark:hover:text-blue-400 transition-all duration-300 cursor-pointer')
                ->dispatch('openEditDepartmentModal', ['department' => $row->id]);
        }

        if ($this->canManage('departments.delete')) {
            $actions[] = Button::add('delete')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700 dark:group-hover:text-red-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 dark:hover:bg-red-800 dark:hover:text-red-400 transition-all duration-300 cursor-pointer')
                ->call('confirmDeleteDepartment', ['id' => $row->id]);
        }

        if ($this->canManage('departments.restore')) {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->call('confirmRestoreDepartment', ['id' => $row->id]);
        }

        return $actions;
    }

    public function actionRules($row): array
    {
        return [
            Rule::button('edit')
                ->when(fn ($row) => $row->trashed())
                ->hide(),
            Rule::button('delete')
                ->when(fn ($row) => $row->trashed())
                ->hide(),
            Rule::button('restore')
                ->when(fn ($row) => ! $row->trashed())
                ->hide(),
        ];
    }

    public function confirmDeleteDepartment(array $params): void
    {
        $this->ensureCanManage('departments.delete');

        $department = $this->findManagedDepartment((int) $params['id']);

        $this->dialog()
            ->question('Warning!', 'Are you sure you want to delete '.e($department->code).' - '.e($department->name).'?')
            ->confirm('Yes, delete', 'deleteDepartment', $department->id)
            ->cancel('Cancel')
            ->send();
    }

    public function deleteDepartment(int $id): void
    {
        $this->ensureCanManage('departments.delete');

        $department = $this->findManagedDepartment($id);

        try {
            $department->delete();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Deleted', 'Department moved to trash.')->send();
        } catch (Exception $e) {
            Log::error('Department Deletion Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to delete department. Please try again or contact support.')->send();
        }
    }

    public function confirmRestoreDepartment(array $params): void
    {
        $this->ensureCanManage('departments.restore');

        $department = $this->findManagedDepartment((int) $params['id'], true);

        $this->dialog()
            ->question('Restore?', 'Are you sure you want to restore '.e($department->code).' - '.e($department->name).'?')
            ->confirm('Yes, restore', 'restoreDepartment', $department->id)
            ->cancel('Cancel')
            ->send();
    }

    public function restoreDepartment(int $id): void
    {
        $this->ensureCanManage('departments.restore');

        $department = $this->findManagedDepartment($id, true);

        try {
            $department->restore();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Restored', 'Department has been restored.')->send();
        } catch (Exception $e) {
            Log::error('Department Restoration Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to restore department. Please try again or contact support.')->send();
        }
    }

    protected function findManagedDepartment(int $id, bool $includeTrashed = false): Department
    {
        $query = Department::query()
            ->where('id', $id)
            ->where('college_id', $this->collegeId);

        if ($includeTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }
}
