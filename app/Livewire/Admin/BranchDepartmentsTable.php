<?php

namespace App\Livewire\Admin;

use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
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
        return [
            Filter::inputText('code')->operators(['contains', 'is', 'is_not']),
            Filter::inputText('name')->operators(['contains', 'is', 'is_not']),
            Filter::datepicker('created_at_formatted', 'created_at'),
        ];
    }

    public function actions(Department $row): array
    {
        $actions = [];

        if (! $row->trashed()) {
            $actions[] = Button::add('view')
                ->slot('View', ['class' => 'block md:hidden'])
                ->icon('default-eye', ['class' => 'w-4 h-4 text-primary-500 group-hover:text-primary-700'])
                ->class('group flex items-center gap-1 text-xs text-primary-500 rounded border border-primary-500 px-2 py-1 hover:text-primary-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer');

            $actions[] = Button::add('delete')
                ->slot('Remove', ['class' => 'block md:hidden'])
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700'])
                ->class('group flex items-center gap-1 text-xs text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmDelete', ['id' => $row->id]);
        } else {
            $actions[] = Button::add('restore')
                ->slot('Restore', ['class' => 'block md:hidden'])
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-green-500 group-hover:text-green-700'])
                ->class('group flex items-center gap-1 text-xs text-green-500 rounded border border-green-500 px-2 py-1 hover:text-green-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmRestore', ['id' => $row->id]);
        }

        return $actions;
    }
}
