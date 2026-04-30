<?php

namespace App\Livewire\Tables\CollegeAdmin;

use App\Models\Subject;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
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

final class SubjectsTable extends PowerGridComponent
{
    use CanManage, Interactions, WithExport;

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
                ->fixedColumns('code', 'title', Responsive::ACTIONS_COLUMN_NAME),
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

            Column::action('Action'),
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

    public function actions(Subject $row): array
    {
        $actions = [];

        if (! $row->trashed()) {
            if ($this->canManage('subjects.update')) {
                $actions[] = Button::add('edit')
                    ->slot('Edit')
                    ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700 dark:group-hover:text-blue-400'])
                    ->class('group flex items-center gap-1 text-xs font-bold text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 dark:hover:bg-blue-800 dark:hover:text-blue-400 transition-all duration-300 cursor-pointer')
                    ->dispatch('openEditSubjectModal', ['subject' => $row->id]);
            }

            if ($this->canManage('subjects.delete')) {
                $actions[] = Button::add('delete')
                    ->slot('Remove')
                    ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700 dark:group-hover:text-red-400'])
                    ->class('group flex items-center gap-1 text-xs font-bold text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 dark:hover:bg-red-800 dark:hover:text-red-400 transition-all duration-300 cursor-pointer')
                    ->call('confirmDeleteSubject', ['id' => $row->id]);
            }
        } elseif ($this->canManage('subjects.restore')) {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->call('confirmRestoreSubject', ['id' => $row->id]);
        }

        return $actions;
    }

    public function confirmDeleteSubject(array $params): void
    {
        $this->ensureCanManage('subjects.delete');

        $subject = $this->findSubject((int) $params['id']);

        $this->dialog()
            ->question('Warning!', 'Are you sure you want to delete '.e($subject->code).' - '.e($subject->title).'?')
            ->confirm('Yes, delete', 'deleteSubject', $subject->id)
            ->cancel('Cancel')
            ->send();
    }

    #[On('deleteSubject')]
    public function deleteSubject(int $id): void
    {
        $this->ensureCanManage('subjects.delete');

        $subject = $this->findSubject($id);

        try {
            $subject->delete();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Deleted', 'Subject moved to trash.')->send();
        } catch (\Exception $e) {
            Log::error('Subject Deletion Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to delete subject. Please try again or contact support.')->send();
        }
    }

    public function confirmRestoreSubject(array $params): void
    {
        $this->ensureCanManage('subjects.restore');

        $subject = $this->findSubject((int) $params['id'], true);

        $this->dialog()
            ->question('Restore?', 'Are you sure you want to restore '.e($subject->code).' - '.e($subject->title).'?')
            ->confirm('Yes, restore', 'restoreSubject', $subject->id)
            ->cancel('Cancel')
            ->send();
    }

    #[On('restoreSubject')]
    public function restoreSubject(int $id): void
    {
        $this->ensureCanManage('subjects.restore');

        $subject = $this->findSubject($id, true);

        try {
            $subject->restore();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Restored', 'Subject has been restored.')->send();
        } catch (\Exception $e) {
            Log::error('Subject Restoration Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to restore subject. Please try again or contact support.')->send();
        }
    }

    protected function findSubject(int $id, bool $includeTrashed = false): Subject
    {
        $query = Subject::query()->where('id', $id);

        if ($includeTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }
}
