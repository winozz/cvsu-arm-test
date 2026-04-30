<?php

namespace App\Livewire\Tables\DeptAdmin;

use App\Models\Schedule;
use App\Traits\CanManage;
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

final class SchedulesTable extends PowerGridComponent
{
    use CanManage, WithExport;

    public string $tableName = 'schedulesTable';

    public int $campusId = 0;

    public int $collegeId = 0;

    public ?int $departmentId = null;

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        return [
            PowerGrid::exportable(fileName: 'schedules-list')
                ->striped()
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),

            PowerGrid::header()
                ->showSearchInput()
                ->showToggleColumns(),

            PowerGrid::footer()
                ->showPerPage(25, [10, 25, 50, 100])
                ->showRecordCount(),

            PowerGrid::responsive()
                ->fixedColumns('sched_code', 'subject_label', Responsive::ACTIONS_COLUMN_NAME),
        ];
    }

    public function datasource(): Builder
    {
        return Schedule::query()
            ->with(['subject', 'sections'])
            ->where('campus_id', $this->campusId)
            ->where('college_id', $this->collegeId)
            ->when(filled($this->departmentId), fn ($q) => $q->where('department_id', $this->departmentId));
    }

    public function relationSearch(): array
    {
        return [
            'subject' => ['code', 'title'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('sched_code')
            ->add('subject_label', fn (Schedule $s) => $s->subject
                ? $s->subject->code.' – '.$s->subject->title
                : '—')
            ->add('section_label', fn (Schedule $s) => $s->sections->first()?->computed_section_name ?? '—')
            ->add('section_type_label', fn (Schedule $s) => $s->sections->first()?->section_type ?? '—')
            ->add('semester')
            ->add('school_year')
            ->add('slots')
            ->add('status_badge', fn (Schedule $s) => $this->statusBadge($s->status));
    }

    public function columns(): array
    {
        return [
            Column::make('Code', 'sched_code')
                ->sortable()
                ->searchable(),

            Column::make('Subject', 'subject_label')
                ->searchable(),

            Column::make('Section', 'section_label'),

            Column::make('Type', 'section_type_label'),

            Column::make('Semester', 'semester')
                ->sortable()
                ->searchable(),

            Column::make('School Year', 'school_year')
                ->sortable()
                ->searchable(),

            Column::make('Slots', 'slots')
                ->sortable(),

            Column::make('Status', 'status_badge', 'status')
                ->sortable(),

            Column::action('Actions'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('status', 'status')
                ->dataSource(collect(Schedule::STATUSES)->map(fn ($s) => ['id' => $s, 'name' => str_replace('_', ' ', ucfirst($s))])->values()->all())
                ->optionValue('id')
                ->optionLabel('name'),

            Filter::inputText('semester', 'semester'),
            Filter::inputText('school_year', 'school_year'),
        ];
    }

    public function actions($row): array
    {
        $actions = [];

        if ($this->canManage('schedules.assign') && ! in_array($row->status, ['published'], true)) {
            $actions[] = Button::add('plot')
                ->slot('Plot')
                ->icon('default-pencil-square', ['class' => 'w-4 h-4'])
                ->class('flex items-center gap-1 text-xs text-blue-600 rounded border border-blue-500 px-2 py-1 hover:bg-blue-50 transition')
                ->route('schedules.plot', ['schedule' => $row->id]);
            // ->route('users.show', ['user' => $row->id]);
        }

        return $actions;
    }

    protected function statusBadge(string $status): string
    {
        $color = match ($status) {
            'draft' => 'text-zinc-500 bg-zinc-100 border-zinc-300',
            'pending_service_acceptance' => 'text-amber-700 bg-amber-100 border-amber-300',
            'pending_plotting' => 'text-blue-700 bg-blue-100 border-blue-300',
            'plotted' => 'text-emerald-700 bg-emerald-100 border-emerald-300',
            'published' => 'text-violet-700 bg-violet-100 border-violet-300',
            default => 'text-zinc-500 bg-zinc-100 border-zinc-300',
        };

        $label = str_replace('_', ' ', ucwords($status, '_'));

        return "<span class=\"inline-flex rounded-full border px-2 py-0.5 text-xs font-medium {$color}\">{$label}</span>";
    }
}
