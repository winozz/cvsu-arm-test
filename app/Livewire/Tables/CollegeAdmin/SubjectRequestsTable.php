<?php

namespace App\Livewire\Tables\CollegeAdmin;

use App\Models\SubjectAssignmentRequest;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class SubjectRequestsTable extends PowerGridComponent
{
    use CanManage;

    public int $campusId;

    public ?int $collegeId = null;

    public int $userId;

    public string $direction = 'incoming';

    public string $tableName = 'subjectRequestsIncomingTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->tableName = $this->direction === 'outgoing'
            ? 'subjectRequestsOutgoingTable'
            : 'subjectRequestsIncomingTable';

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
        return SubjectAssignmentRequest::query()
            ->with([
                'subject',
                'requestedBy',
                'sourceCampus',
                'sourceCollege',
                'targetCampus',
                'targetCollege',
            ])
            ->where('status', SubjectAssignmentRequest::STATUS_PENDING)
            ->when(
                $this->direction === 'incoming',
                fn (Builder $query) => $query
                    ->where('target_campus_id', $this->campusId)
                    ->when(
                        filled($this->collegeId),
                        fn (Builder $builder) => $builder->where('target_college_id', $this->collegeId),
                        fn (Builder $builder) => $builder->whereNull('target_college_id')
                    ),
                fn (Builder $query) => $query
                    ->where('source_campus_id', $this->campusId)
                    ->when(
                        filled($this->collegeId),
                        fn (Builder $builder) => $builder->where('source_college_id', $this->collegeId),
                        fn (Builder $builder) => $builder->whereNull('source_college_id')
                    )
            );
    }

    public function relationSearch(): array
    {
        return [
            'subject' => ['code', 'title'],
            'requestedBy' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('subject_code', fn (SubjectAssignmentRequest $request) => $request->subject?->code ?? '-')
            ->add('subject_title', fn (SubjectAssignmentRequest $request) => $request->subject?->title ?? '-')
            ->add('request_type_label', fn (SubjectAssignmentRequest $request) => $request->request_type_label)
            ->add('source_scope_label', fn (SubjectAssignmentRequest $request) => $request->source_scope_label)
            ->add('target_scope_label', fn (SubjectAssignmentRequest $request) => $request->target_scope_label)
            ->add('requested_by_name', fn (SubjectAssignmentRequest $request) => $request->requestedBy?->name ?? '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Code', 'subject_code')
                ->sortable()
                ->searchable(),
            Column::make('Title', 'subject_title')
                ->sortable()
                ->searchable(),
            Column::make('Type', 'request_type_label', 'request_type')
                ->sortable()
                ->searchable(),
            Column::make('Source', 'source_scope_label'),
            Column::make('Target', 'target_scope_label'),
            Column::make('Requested By', 'requested_by_name')
                ->hidden(isHidden: $this->direction === 'outgoing', isForceHidden: false),
            Column::action('Action'),
        ];
    }

    public function actions(SubjectAssignmentRequest $row): array
    {
        if (! $this->canManage('subjects.update')) {
            return [];
        }

        if ($this->direction === 'incoming') {
            return [
                Button::add('accept')
                    ->slot('Accept')
                    // ->icon('default-check-circle', ['class' => 'w-4 h-4 text-emerald-500 group-hover:text-emerald-700 dark:group-hover:text-emerald-400'])
                    ->class('group flex items-center gap-1 text-xs font-bold text-emerald-500 rounded border border-emerald-500 px-2 py-1 hover:text-emerald-700 hover:bg-zinc-100 dark:hover:bg-emerald-800 dark:hover:text-emerald-400 transition-all duration-300 cursor-pointer')
                    ->dispatch('confirmAcceptSubjectRequest', ['request' => $row->id]),
                Button::add('reject')
                    ->slot('Reject')
                    // ->icon('default-x-circle', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700 dark:group-hover:text-red-400'])
                    ->class('group flex items-center gap-1 text-xs font-bold text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 dark:hover:bg-red-800 dark:hover:text-red-400 transition-all duration-300 cursor-pointer')
                    ->dispatch('confirmRejectSubjectRequest', ['request' => $row->id]),
            ];
        }

        if ((int) $row->requested_by !== $this->userId) {
            return [];
        }

        return [
            Button::add('cancel')
                ->slot('Cancel')
                // ->icon('default-no-symbol', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700 dark:group-hover:text-amber-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 dark:hover:bg-amber-800 dark:hover:text-amber-400 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmCancelSubjectRequest', ['request' => $row->id]),
        ];
    }
}
