<?php

namespace App\Livewire\Tables\CollegeAdmin;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class SubjectsTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'subjectsTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'subjects-list')
                ->striped()
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),

            PowerGrid::header()
                ->showSearchInput()
                ->showToggleColumns()
                ->showSoftDeletes(showMessage: true),

            PowerGrid::footer()
                ->showPerPage(25, [10, 25, 50, 100, 0])
                ->showRecordCount(),

            PowerGrid::responsive()
                ->fixedColumns('code', 'title'),
        ];
    }

    public function datasource(): Builder
    {
        return Subject::query()
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
            ->add('title')
            ->add('description')
            ->add('lecture_units')
            ->add('laboratory_units')
            ->add('is_credit')
            ->add('is_active')
            ->add('units_label', fn (Subject $model) => $model->units_label)
            ->add('credit_label', fn (Subject $model) => $model->credit_label)
            ->add('availability', fn (Subject $model) => $model->is_active ? 'Active' : 'Inactive')
            ->add(
                'created_at_formatted',
                fn (Subject $model) => $model->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
            );
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),

            Column::make('Title', 'title')
                ->sortable()
                ->searchable(),

            Column::make('Description', 'description')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Units', 'units_label'),

            Column::make('Credit Type', 'credit_label', 'is_credit')
                ->sortable()
                ->searchable(),

            Column::make('Availability', 'availability', 'is_active')
                ->sortable()
                ->searchable(),

            Column::make('Created at', 'created_at_formatted', 'created_at')
                ->sortable()
                ->hidden(isHidden: true, isForceHidden: false),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('is_credit')
                ->dataSource([
                    ['id' => 1, 'name' => 'Credit'],
                    ['id' => 0, 'name' => 'Non-credit'],
                ])
                ->optionLabel('name')
                ->optionValue('id')
                ->builder(fn (Builder $query, $value) => filled($value) ? $query->where('is_credit', (int) $value) : $query),

            Filter::select('is_active')
                ->dataSource([
                    ['id' => 1, 'name' => 'Active'],
                    ['id' => 0, 'name' => 'Inactive'],
                ])
                ->optionLabel('name')
                ->optionValue('id')
                ->builder(fn (Builder $query, $value) => filled($value) ? $query->where('is_active', (int) $value) : $query),

            Filter::datetimepicker('created_at'),
        ];
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
