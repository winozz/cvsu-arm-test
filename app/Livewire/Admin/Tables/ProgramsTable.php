<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Program;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Facades\Rule;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;

final class ProgramsTable extends PowerGridComponent
{
    use CanManage, WithExport;

    public int $collegeId;

    public string $tableName = 'programsTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'programs-list')
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
        return $this->baseProgramQuery();
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
            ->add('no_of_years')
            ->add('level')
            ->add('level_label', fn (Program $model) => $model->level_label)
            ->add('availability', fn (Program $model) => $model->is_active ? 'Active' : 'Inactive');
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

            Column::make('No of years', 'no_of_years')
                ->hidden(isHidden: true, isForceHidden: false),

            Column::make('Level', 'level_label', 'level')
                ->sortable()
                ->searchable(),

            Column::make('Availability', 'availability', 'is_active')
                ->sortable()
                ->searchable()
                ->hidden(isHidden: true, isForceHidden: false),

            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('level', 'level')
                ->dataSource($this->levelFilterOptions())
                ->optionLabel('name')
                ->optionValue('id')
                ->builder(fn (Builder $query, $value) => filled($value) ? $query->where('level', $value) : $query),

            Filter::select('is_active')
                ->dataSource($this->availabilityFilterOptions())
                ->optionLabel('name')
                ->optionValue('id')
                ->builder(fn (Builder $query, $value) => filled($value) ? $query->where('is_active', (int) $value) : $query),
        ];
    }

    protected function baseProgramQuery(): Builder
    {
        return Program::query()
            ->whereHas('colleges', fn ($query) => $query->whereKey($this->collegeId))
            ->when($this->softDeletes === 'withTrashed', fn ($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn ($query) => $query->onlyTrashed());
    }

    protected function levelFilterOptions(): array
    {
        return $this->baseProgramQuery()
            ->whereNotNull('level')
            ->where('level', '!=', '')
            ->distinct()
            ->orderBy('level')
            ->pluck('level')
            ->map(fn (string $level) => [
                'id' => $level,
                'name' => Program::LEVELS[$level] ?? $level,
            ])
            ->values()
            ->all();
    }

    protected function availabilityFilterOptions(): array
    {
        return $this->baseProgramQuery()
            ->distinct()
            ->orderByDesc('is_active')
            ->pluck('is_active')
            ->map(fn ($isActive) => (int) $isActive)
            ->unique()
            ->map(fn (int $isActive) => [
                'id' => $isActive,
                'name' => $isActive === 1 ? 'Active' : 'Inactive',
            ])
            ->values()
            ->all();
    }

    public function actions(Program $row): array
    {
        $actions = [];

        if ($this->canManage('programs.update')) {
            $actions[] = Button::add('edit')
                ->slot('Edit')
                ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700 dark:group-hover:text-blue-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 dark:hover:bg-blue-800 dark:hover:text-blue-400 transition-all duration-300 cursor-pointer')
                ->dispatch('openEditProgramModal', ['program' => $row->id]);
        }

        if ($this->canManage('programs.delete')) {
            $actions[] = Button::add('delete')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700 dark:group-hover:text-red-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 dark:hover:bg-red-800 dark:hover:text-red-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmDeleteProgram', ['id' => $row->id]);
        }

        if ($this->canManage('programs.restore')) {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmRestoreProgram', ['id' => $row->id]);
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
}
