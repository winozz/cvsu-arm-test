<?php

namespace App\Livewire\Admin;

use App\Models\FacultyProfile;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Responsive;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;

final class FacultyProfilesTable extends PowerGridComponent
{
    public string $tableName = 'facultyProfilesTable';

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'faculty-list')
                ->striped()
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),

            PowerGrid::header()
                ->showSearchInput()
                ->showSoftDeletes(showMessage: true),

            PowerGrid::footer()
                ->showPerPage()
                ->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return FacultyProfile::query()
            ->with(['user', 'branch', 'department'])
            ->when($this->softDeletes === 'withTrashed', fn($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn($query) => $query->onlyTrashed());
    }

    public function relationSearch(): array
    {
        return [
            'branch' => ['name'],
            'department' => ['name'],
            'user' => ['name', 'email'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('full_name', fn(FacultyProfile $model) => trim($model->first_name . ' ' . $model->last_name))
            ->add('email')
            ->add('academic_rank', fn(FacultyProfile $model) => $model->academic_rank ?: '-')
            ->add('branch_name', fn(FacultyProfile $model) => $model->branch ? $model->branch->name : '-')
            ->add('department_name', fn(FacultyProfile $model) => $model->department ? $model->department->name : '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('Name', 'full_name')->sortable()->searchable(),
            Column::make('Email', 'email')->sortable()->searchable(),
            Column::make('Academic Rank', 'academic_rank')->sortable()->searchable(),
            Column::make('Campus', 'branch_name')->searchable(),
            Column::make('Department', 'department_name')->searchable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::inputText('full_name')->operators(['contains']),
            Filter::inputText('email')->operators(['contains']),
            Filter::inputText('academic_rank')->operators(['contains']),
        ];
    }

    public function actions(FacultyProfile $row): array
    {
        $actions = [];

        if (! $row->trashed()) {
            $actions[] = Button::add('view')
                ->slot('View')
                ->icon('default-eye', ['class' => 'w-4 h-4 text-primary-500 group-hover:text-primary-700'])
                ->class('group flex items-center gap-1 text-xs text-primary-500 rounded border border-primary-500 px-2 py-1 hover:text-primary-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->route('admin.faculty-profiles.show', ['facultyProfile' => $row->id]);

            $actions[] = Button::add('delete')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700'])
                ->class('group flex items-center gap-1 text-xs text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmDelete', ['id' => $row->id]);
        } else {
            $actions[] = Button::add('restore')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700'])
                ->class('group flex items-center gap-1 text-xs text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->dispatch('confirmRestore', ['id' => $row->id]);
        }

        return $actions;
    }
}
