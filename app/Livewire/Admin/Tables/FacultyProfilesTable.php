<?php

namespace App\Livewire\Admin\Tables;

use App\Models\FacultyProfile;
use App\Models\EmployeeProfile;
use App\Traits\CanManage;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

final class FacultyProfilesTable extends PowerGridComponent
{
    use CanManage, Interactions, WithExport;

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
        $profile = $this->employeeProfile();

        return FacultyProfile::query()
            ->with(['user', 'campus', 'college', 'department'])
            ->when(
                filled($profile->department_id),
                fn ($query) => $query->where('department_id', $profile->department_id),
                fn ($query) => $query->where('college_id', $profile->college_id)
            )
            ->when($this->softDeletes === 'withTrashed', fn ($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn ($query) => $query->onlyTrashed());
    }

    public function relationSearch(): array
    {
        return [
            'campus' => ['name'],
            'college' => ['name'],
            'department' => ['name'],
            'user' => ['name', 'email'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('deleted_at')
            ->add('full_name', fn (FacultyProfile $model) => trim($model->first_name.' '.$model->last_name))
            ->add('email')
            ->add('academic_rank', fn (FacultyProfile $model) => $model->academic_rank ?: '-')
            ->add('campus_name', fn (FacultyProfile $model) => $model->campus?->name ?? '-')
            ->add('college_name', fn (FacultyProfile $model) => $model->college?->name ?? '-')
            ->add('department_name', fn (FacultyProfile $model) => $model->department ? $model->department->name : '-');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('Name', 'full_name')->sortable()->searchable(),
            Column::make('Email', 'email')->sortable()->searchable(),
            Column::make('Academic Rank', 'academic_rank')->sortable()->searchable(),
            Column::make('Campus', 'campus_name')->searchable(),
            Column::make('College', 'college_name')->searchable(),
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

    public function actions($row): array
    {
        $actions = [];

        if ($this->canManage('faculty_profiles.view')) {
            $actions[] = Button::add('view-faculty')
                ->slot('View')
                ->icon('default-eye', ['class' => 'w-4 h-4 text-primary-500 group-hover:text-primary-700'])
                ->class('group flex items-center gap-1 text-xs text-primary-500 rounded border border-primary-500 px-2 py-1 hover:text-primary-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->route('department-admin.faculty-profiles.show', ['facultyProfile' => $row->id]);
        }

        if ($this->canManage('faculty_profiles.delete')) {
            $actions[] = Button::add('delete-faculty')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700'])
                ->class('group flex items-center gap-1 text-xs text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->call('confirmDeleteFaculty', ['id' => $row->id]);
        }

        if ($this->canManage('faculty_profiles.restore')) {
            $actions[] = Button::add('restore-faculty')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700'])
                ->class('group flex items-center gap-1 text-xs text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->call('confirmRestoreFaculty', ['id' => $row->id]);
        }

        return $actions;
    }

    public function actionRules($row): array
    {
        return [
            Rule::button('view-faculty')
                ->when(fn ($row) => $row->trashed())
                ->hide(),

            Rule::button('delete-faculty')
                ->when(fn ($row) => $row->trashed())
                ->hide(),

            Rule::button('restore-faculty')
                ->when(fn ($row) => ! $row->trashed())
                ->hide(),
        ];
    }

    public function confirmDeleteFaculty(array $params): void
    {
        $this->ensureCanManage('faculty_profiles.delete');

        $this->findManagedProfile((int) $params['id']);

        $this->dialog()
            ->question('Warning!', 'Are you sure you want to delete this faculty profile?')
            ->confirm('Yes, delete', 'deleteFaculty', (int) $params['id'])
            ->cancel('Cancel')
            ->send();
    }

    public function deleteFaculty(int $id): void
    {
        $this->ensureCanManage('faculty_profiles.delete');

        try {
            $this->findManagedProfile($id)->delete();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Deleted', 'Faculty Profile moved to trash.')->send();
        } catch (Exception $e) {
            Log::error('Faculty Profile Deletion Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to delete faculty profile. Please try again or contact support.')->send();
        }
    }

    public function confirmRestoreFaculty(array $params): void
    {
        $this->ensureCanManage('faculty_profiles.restore');

        $this->findManagedProfile((int) $params['id'], true);

        $this->dialog()
            ->question('Restore?', 'Are you sure you want to restore this faculty profile?')
            ->confirm('Yes, restore', 'restoreFaculty', (int) $params['id'])
            ->cancel('Cancel')
            ->send();
    }

    public function restoreFaculty(int $id): void
    {
        $this->ensureCanManage('faculty_profiles.restore');

        try {
            $this->findManagedProfile($id, true)->restore();
            $this->dispatch('pg:eventRefresh-'.$this->tableName);
            $this->toast()->success('Restored', 'Faculty Profile has been restored.')->send();
        } catch (Exception $e) {
            Log::error('Faculty Profile Restoration Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to restore faculty profile. Please try again or contact support.')->send();
        }
    }

    protected function findManagedProfile(int $id, bool $includeTrashed = false): FacultyProfile
    {
        $profile = $this->employeeProfile();

        $query = FacultyProfile::query()
            ->where('id', $id)
            ->when(
                filled($profile->department_id),
                fn ($q) => $q->where('department_id', $profile->department_id),
                fn ($q) => $q->where('college_id', $profile->college_id)
            );

        if ($includeTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }

    protected function employeeProfile(): EmployeeProfile
    {
        $profile = Auth::user()?->employeeProfile;

        abort_unless($profile && filled($profile->college_id), 403);

        return $profile;
    }
}
