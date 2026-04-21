<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Program;
use App\Traits\CanManage;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
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

final class ProgramsTable extends PowerGridComponent
{
    use CanManage, Interactions, WithExport;

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
                ->call('confirmDeleteProgram', ['id' => $row->id]);
        }

        if ($this->canManage('programs.restore')) {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->call('confirmRestoreProgram', ['id' => $row->id]);
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

    #[On('confirmDeleteProgram')]
    public function confirmDeleteProgram(array $params): void
    {
        $this->ensureCanManage('programs.delete');

        $program = $this->findManagedProgram((int) $params['id']);
        $collegeCount = $program->colleges()->count();
        $message = 'Are you sure you want to remove '.e($program->code).' - '.e($program->title).' from the offered programs?';

        if ($collegeCount > 1) {
            $message .= ' This shared program is offered by '.$collegeCount.' colleges and will be moved to trash for all of them.';
        }

        $this->dialog()
            ->question('Remove Program?', $message)
            ->confirm('Yes, remove', 'deleteProgram', $program->id)
            ->cancel('Cancel')
            ->send();
    }

    public function deleteProgram(int $id): void
    {
        $this->ensureCanManage('programs.delete');

        try {
            $this->findManagedProgram($id)->delete();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Deleted', 'Program moved to trash.')->send();
        } catch (Exception $e) {
            Log::error('Program Deletion Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to delete program. Please try again or contact support.')->send();
        }
    }

    public function confirmRestoreProgram(array $params): void
    {
        $this->ensureCanManage('programs.restore');

        $program = $this->findManagedProgram((int) $params['id'], true);

        $this->dialog()
            ->question('Restore Program?', 'Are you sure you want to restore '.e($program->code).' - '.e($program->title).'?')
            ->confirm('Yes, restore', 'restoreProgram', $program->id)
            ->cancel('Cancel')
            ->send();
    }

    public function restoreProgram(int $id): void
    {
        $this->ensureCanManage('programs.restore');

        try {
            $this->findManagedProgram($id, true)->restore();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Restored', 'Program has been restored.')->send();
        } catch (Exception $e) {
            Log::error('Program Restoration Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to restore program. Please try again or contact support.')->send();
        }
    }

    protected function findManagedProgram(int $id, bool $includeTrashed = false): Program
    {
        $query = Program::query()
            ->whereKey($id)
            ->whereHas('colleges', fn ($query) => $query->whereKey($this->collegeId));

        if ($includeTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }
}
