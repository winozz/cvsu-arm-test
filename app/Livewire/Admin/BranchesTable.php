<?php

namespace App\Livewire\Admin;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
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

final class BranchesTable extends PowerGridComponent
{
    use Interactions, WithExport;

    public string $tableName = 'branchTable';

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
            PowerGrid::exportable(fileName: 'branch-list')
                ->striped()
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),

            PowerGrid::header()
                ->showSearchInput()
                ->showToggleColumns()
                ->showSoftDeletes(showMessage: true),

            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),

            // Enable responsive design for the table
            PowerGrid::responsive()
                ->fixedColumns('code', Responsive::ACTIONS_COLUMN_NAME),
        ];
    }

    public function datasource(): Builder
    {
        return Branch::query()
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
            ->add('code_link', fn (Branch $model) => '<a href="'.route('admin.branches.show', $model->id).'" class="text-primary-500 hover:text-primary-700 dark:hover:text-primary-400 hover:underline font-medium transition-colors">'.e($model->code).'</a>')
            ->add('name')
            ->add('name_link', fn (Branch $model) => '<a href="'.route('admin.branches.show', $model->id).'" class="text-primary-500 hover:text-primary-700 dark:hover:text-primary-400 hover:underline font-medium transition-colors">'.e($model->name).'</a>')
            ->add('type')
            ->add('address');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id'),

            Column::make('Code', 'code_link', 'code')
                ->sortable()
                ->searchable(),

            Column::make('Name', 'name_link', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Campus', 'type')
                ->sortable()
                ->searchable(),

            Column::make('Address', 'address')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            // Select Filter for Campus Type
            Filter::select('type', 'type')
                ->dataSource([
                    ['name' => 'Main', 'id' => 'Main'],
                    ['name' => 'Satellite', 'id' => 'Satellite'],
                ])
                ->optionLabel('name')
                ->optionValue('id'),
        ];
    }

    #[\Livewire\Attributes\On('edit')]
    public function edit($rowId): void
    {
        $this->js('alert('.$rowId.')');
    }

    public function actions(Branch $row): array
    {
        $actions = [];

        if (! $row->trashed()) {
            // Replaced the edit dispatch with a view link
            $actions[] = Button::add('view')
                ->slot('View')
                ->icon('default-eye', ['class' => 'w-4 h-4 text-primary-500 group-hover:text-primary-700 dark:group-hover:text-primary-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-primary-500 rounded border border-primary-500 px-2 py-1 hover:text-primary-700 hover:bg-zinc-100 dark:hover:bg-primary-800 dark:hover:text-primary-400 transition-all duration-300 cursor-pointer')
                ->route('admin.branches.show', ['branch' => $row->id]);

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

    /*
    public function actionRules($row): array
    {
       return [
            // Hide button edit for ID 1
            Rule::button('edit')
                ->when(fn($row) => $row->id === 1)
                ->hide(),
        ];
    }
    */
}
