<?php

namespace App\Livewire\Tables\CollegeAdmin;

use App\Models\Subject;
use App\Models\SubjectAssignmentRequest;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class AllSubjectsTable extends PowerGridComponent
{
    use CanManage;

    public int $campusId;

    public ?int $collegeId = null;

    public string $tableName = 'allSubjectsTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        return [
            PowerGrid::header()
                ->showSearchInput()
                ->showToggleColumns(),
            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return Subject::query()
            ->with(['subjectAssignments.campus', 'subjectAssignments.college'])
            ->where('status', Subject::STATUS_SUBMITTED);
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
            ->add('units_label', fn (Subject $subject) => $subject->units_label)
            ->add('assignees_label', fn (Subject $subject) => $this->assigneesLabel($subject));
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
            Column::make('Assigned To', 'assignees_label'),
            Column::action('Action'),
        ];
    }

    public function actions(Subject $row): array
    {
        $actions = [];

        if (! $this->canManage('subjects.update')) {
            return $actions;
        }

        if ($this->hasCurrentScopeAssignment($row)) {
            $actions[] = Button::add('request-assign')
                ->slot('Request Assign')
                // ->icon('default-paper-airplane', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700 dark:group-hover:text-blue-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 dark:hover:bg-blue-800 dark:hover:text-blue-400 transition-all duration-300 cursor-pointer')
                ->dispatch('openSubjectRequestModal', [
                    'subject' => $row->id,
                    'type' => SubjectAssignmentRequest::TYPE_ASSIGN,
                ]);

            $actions[] = Button::add('request-transfer')
                ->slot('Request Transfer')
                // ->icon('default-arrows-right-left', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->dispatch('openSubjectRequestModal', [
                    'subject' => $row->id,
                    'type' => SubjectAssignmentRequest::TYPE_TRANSFER,
                ]);

            return $actions;
        }

        if ($row->subjectAssignments->isEmpty()) {
            $actions[] = Button::add('assign')
                ->slot('Assign to Me')
                // ->icon('default-plus-circle', ['class' => 'w-4 h-4 text-emerald-500 group-hover:text-emerald-700 dark:group-hover:text-emerald-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-emerald-500 rounded border border-emerald-500 px-2 py-1 hover:text-emerald-700 hover:bg-zinc-100 dark:hover:bg-emerald-800 dark:hover:text-emerald-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmAssignSubject', ['subject' => $row->id]);
        }

        return $actions;
    }

    protected function assigneesLabel(Subject $subject): string
    {
        $labels = $subject->subjectAssignments
            ->map(function ($assignment): string {
                if ($assignment->college) {
                    return trim($assignment->campus?->code.' / '.$assignment->college->code, ' /');
                }

                return $assignment->campus?->code ?? '-';
            })
            ->unique()
            ->values()
            ->all();

        return $labels !== [] ? implode(', ', $labels) : 'Not assigned';
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
