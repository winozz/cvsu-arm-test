<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Campus;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use TallStackUi\Traits\Interactions;

final class CampusesTable extends PowerGridComponent
{
    use CanManage, Interactions;

    public string $tableName = 'campusesTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'campuses-list')
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
        return Campus::query()
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
            ->add('name')
            ->add('code')
            ->add('description');
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id'),
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),

            Column::make('Description', 'description')
                ->sortable()
                ->searchable(),

            Column::action('Action'),
        ];
    }

    public function actions(Campus $row): array
    {
        $actions = [];

        if ($this->canManage('colleges.view')) {
            $actions[] = Button::add('view-campus')
                ->slot('View')
                ->icon('default-eye', ['class' => 'w-4 h-4 text-primary-500 group-hover:text-primary-700 dark:group-hover:text-primary-400'])
                ->class('group flex items-center gap-1 text-xs font-bold text-primary-500 rounded border border-primary-500 px-2 py-1 hover:text-primary-700 hover:bg-zinc-100 dark:hover:bg-primary-800 dark:hover:text-primary-400 transition-all duration-300 cursor-pointer')
                ->route('admin.campuses.show', ['campus' => $row->id]);
        }

        return $actions;
    }
}
