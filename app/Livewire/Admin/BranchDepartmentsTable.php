<?php

namespace App\Livewire\Admin;

use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use TallStackUi\Traits\Interactions;

final class BranchDepartmentsTable extends PowerGridComponent
{
    use Interactions, WithExport;

    // This property will receive the branch ID from the parent view
    public int $branchId;

    public string $tableName = 'branchDepartmentsTable';

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
        // Scope the query to only fetch departments for this specific branch
        return Department::query()
            ->where('branch_id', $this->branchId)
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
            ->add('code')
            ->add('name')
            ->add('is_active', fn ($row) => $row->is_active ? 'Active' : 'Inactive')
            ->add('created_at_formatted', fn (Department $model) => $model->created_at->format('d/m/Y'))
            ->add('status', fn ($row) => $row->trashed() ? 'Deleted' : 'Active');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id'),

            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),

            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Status', 'status')
                ->sortable()
                ->searchable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->visibleInExport(false)
                ->sortable(),

            Column::make('Created at', 'created_at')
                ->visibleInExport(true)
                ->hidden()
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [];
    }

    public function actions(Department $row): array
    {
        $actions = [];

        if (! $row->trashed()) {
            $actions[] = Button::add('edit')
                ->slot('Edit')
                ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700 dark:group-hover:text-blue-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 dark:hover:bg-blue-800 dark:hover:text-blue-400 transition-all duration-300 cursor-pointer')
                ->dispatch('editDepartment', ['department' => $row->id]);

            $actions[] = Button::add('delete')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700 dark:group-hover:text-red-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 dark:hover:bg-red-800 dark:hover:text-red-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmDelete', ['id' => $row->id]);
        } else {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmRestore', ['id' => $row->id]);
        }

        return $actions;
    }
}
