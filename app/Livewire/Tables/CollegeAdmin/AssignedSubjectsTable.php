<?php

namespace App\Livewire\Tables\CollegeAdmin;

use App\Models\Subject;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class AssignedSubjectsTable extends PowerGridComponent
{
    use CanManage;

    public int $campusId;

    public ?int $collegeId = null;

    public int $userId;

    public string $tableName = 'assignedSubjectsTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        return [
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
        return $this->baseSubjectQuery();
    }

    public function relationSearch(): array
    {
        return [
            'programs' => ['code', 'title'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('code')
            ->add('title')
            ->add('units_label', fn (Subject $subject) => $subject->units_label)
            ->add('status_label', fn (Subject $subject) => $subject->status_label)
            ->add('programs_label', fn (Subject $subject) => $subject->programs->pluck('code')->implode(', ') ?: '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),
            Column::make('Title', 'title')
                ->sortable()
                ->searchable(),
            Column::make('Units', 'units_label'),
            Column::make('Status', 'status_label', 'status')
                ->sortable()
                ->searchable(),
            Column::make('Programs', 'programs_label')
                ->hidden(isHidden: true, isForceHidden: false),
            Column::action('Action'),
        ];
    }

    public function actions(Subject $row): array
    {
        $actions = [];

        if (! $row->trashed() && $row->status === Subject::STATUS_DRAFT && (int) $row->created_by === $this->userId) {
            if ($this->canManage('subjects.update')) {
                $actions[] = Button::add('edit')
                    ->slot('Edit')
                    // ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700 dark:group-hover:text-blue-400'])
                    ->class('group flex items-center gap-1 text-xs font-bold text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 dark:hover:bg-blue-800 dark:hover:text-blue-400 transition-all duration-300 cursor-pointer')
                    ->dispatch('openEditSubjectModal', ['subject' => $row->id]);

                $actions[] = Button::add('submit')
                    ->slot('Submit')
                    // ->icon('default-check-circle', ['class' => 'w-4 h-4 text-emerald-500 group-hover:text-emerald-700 dark:group-hover:text-emerald-400'])
                    ->class('group flex items-center gap-1 text-xs font-bold text-emerald-500 rounded border border-emerald-500 px-2 py-1 hover:text-emerald-700 hover:bg-zinc-100 dark:hover:bg-emerald-800 dark:hover:text-emerald-400 transition-all duration-300 cursor-pointer')
                    ->dispatch('confirmSubmitSubject', ['subject' => $row->id]);
            }

            if ($this->canManage('subjects.delete')) {
                $actions[] = Button::add('delete')
                    ->slot('Trash')
                    // ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700 dark:group-hover:text-red-400'])
                    ->class('group flex items-center gap-1 text-xs font-bold text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 dark:hover:bg-red-800 dark:hover:text-red-400 transition-all duration-300 cursor-pointer')
                    ->dispatch('confirmDeleteSubjectDraft', ['subject' => $row->id]);
            }
        }

        if (! $row->trashed() && $row->status === Subject::STATUS_SUBMITTED && $this->hasCurrentScopeAssignment($row) && $this->canManage('subjects.update')) {
            $actions[] = Button::add('unassign')
                ->slot('Unassign')
                // ->icon('default-minus-circle', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmUnassignSubject', ['subject' => $row->id]);
        }

        if ($row->trashed() && $row->status === Subject::STATUS_DRAFT && $this->canManage('subjects.restore')) {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                // ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmRestoreSubjectDraft', ['subject' => $row->id]);
        }

        return $actions;
    }

    protected function baseSubjectQuery(): Builder
    {
        return Subject::query()
            ->with(['programs:id,code,title', 'subjectAssignments'])
            ->where(function (Builder $query): void {
                $query->where(function (Builder $draftQuery): void {
                    $draftQuery
                        ->where('status', Subject::STATUS_DRAFT)
                        ->where('created_by', $this->userId);
                })->orWhere(function (Builder $submittedQuery): void {
                    $submittedQuery
                        ->where('status', Subject::STATUS_SUBMITTED)
                        ->whereHas('subjectAssignments', fn (Builder $assignmentQuery) => $this->applyManagedScope($assignmentQuery));
                });
            })
            ->when($this->softDeletes === 'withTrashed', fn (Builder $query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn (Builder $query) => $query->onlyTrashed());
    }

    protected function applyManagedScope(Builder $query): Builder
    {
        return $query
            ->where('campus_id', $this->campusId)
            ->when(
                filled($this->collegeId),
                fn (Builder $builder) => $builder->where('college_id', $this->collegeId),
                fn (Builder $builder) => $builder->whereNull('college_id')
            );
    }

    protected function hasCurrentScopeAssignment(Subject $subject): bool
    {
        return $subject->subjectAssignments->contains(function ($assignment): bool {
            if ((int) $assignment->campus_id !== $this->campusId) {
                return false;
            }

            if (filled($this->collegeId)) {
                return (int) $assignment->college_id === $this->collegeId;
            }

            return blank($assignment->college_id);
        });
    }
}
