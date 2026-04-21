<?php

namespace App\Livewire\Admin\Tables;

use App\Models\Room;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
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

final class RoomsTable extends PowerGridComponent
{
    use CanManage, Interactions, WithExport;

    public string $tableName = 'roomsTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'rooms-list')
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
        return Room::query()
            ->with(['campus', 'college', 'department'])
            ->where('department_id', $this->departmentId())
            ->when($this->softDeletes === 'withTrashed', fn ($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn ($query) => $query->onlyTrashed());
    }

    public function relationSearch(): array
    {
        return [
            'campus' => ['name'],
            'college' => ['name'],
            'department' => ['name'],
        ];
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('display_name', fn (Room $model) => $model->display_name)
            ->add('floor_label', fn (Room $model) => filled($model->floor_no) ? 'Floor '.$model->floor_no : '-')
            ->add('type_label', fn (Room $model) => $model->type_label)
            ->add('campus_name', fn (Room $model) => $model->campus?->name ?? '-')
            ->add('college_name', fn (Room $model) => $model->college?->name ?? '-')
            ->add('department_name', fn (Room $model) => $model->department?->name ?? '-')
            ->add('location_text', fn (Room $model) => $model->location ?: '-')
            ->add('description_text', fn (Room $model) => $model->description ?: '-')
            ->add('availability', fn (Room $model) => $model->is_active ? 'Active' : 'Inactive')
            ->add('status_label', fn (Room $model) => $model->status_label);
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')
                ->hidden(isHidden: true, isForceHidden: false),
            Column::make('Room', 'display_name', 'name')
                ->sortable()
                ->searchable(),
            Column::make('Type', 'type_label', 'type')
                ->sortable()
                ->searchable(),
            Column::make('Floor', 'floor_label', 'floor_no')
                ->sortable()
                ->searchable(),
            Column::make('Campus', 'campus_name')
                ->hidden(isHidden: true, isForceHidden: false)
                ->searchable(),
            Column::make('College', 'college_name')
                ->hidden(isHidden: true, isForceHidden: false)
                ->searchable(),
            Column::make('Department', 'department_name')
                ->searchable(),
            Column::make('Location', 'location_text')
                ->searchable(),
            Column::make('Description', 'description_text')
                ->hidden(isHidden: true, isForceHidden: false)
                ->searchable(),
            Column::make('Availability', 'availability', 'is_active')
                ->sortable(),
            Column::make('Status', 'status_label', 'status')
                ->sortable()
                ->searchable(),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
            Filter::select('type')
                ->dataSource($this->enumOptions(Room::TYPES))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => filled($value) ? $query->where('type', $value) : $query),

            Filter::select('status')
                ->dataSource($this->enumOptions(Room::STATUSES))
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => filled($value) ? $query->where('status', $value) : $query),

            Filter::select('is_active')
                ->dataSource([
                    ['id' => 1, 'name' => 'Active'],
                    ['id' => 0, 'name' => 'Inactive'],
                ])
                ->optionValue('id')
                ->optionLabel('name')
                ->builder(fn (Builder $query, $value) => filled($value) ? $query->where('is_active', (int) $value) : $query),
        ];
    }

    protected function enumOptions(array $options): array
    {
        return collect($options)
            ->map(fn (string $name, string $id) => [
                'id' => $id,
                'name' => $name,
            ])
            ->values()
            ->all();
    }

    public function actions($row): array
    {
        $actions = [];

        if ($this->canManage('rooms.update')) {
            $actions[] = Button::add('edit-room')
                ->slot('Edit')
                ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700'])
                ->class('group flex items-center gap-1 text-xs text-blue-500 rounded border border-blue-500 px-2 py-1 hover:text-blue-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->dispatch('openEditRoomModal', ['room' => $row->id]);
        }

        if ($this->canManage('rooms.delete')) {
            $actions[] = Button::add('delete-room')
                ->slot('Remove')
                ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700'])
                ->class('group flex items-center gap-1 text-xs text-red-500 rounded border border-red-500 px-2 py-1 hover:text-red-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->call('confirmDeleteRoom', ['id' => $row->id]);
        }

        if ($this->canManage('rooms.restore')) {
            $actions[] = Button::add('restore-room')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700'])
                ->class('group flex items-center gap-1 text-xs text-amber-500 rounded border border-amber-500 px-2 py-1 hover:text-amber-700 hover:bg-zinc-100 transition-all duration-300 cursor-pointer')
                ->call('confirmRestoreRoom', ['id' => $row->id]);
        }

        return $actions;
    }

    public function actionRules($row): array
    {
        return [
            Rule::button('edit-room')
                ->when(fn ($row) => $row->trashed())
                ->hide(),
            Rule::button('delete-room')
                ->when(fn ($row) => $row->trashed())
                ->hide(),
            Rule::button('restore-room')
                ->when(fn ($row) => ! $row->trashed())
                ->hide(),
        ];
    }

    public function confirmDeleteRoom(array $params): void
    {
        $this->ensureCanManage('rooms.delete');

        $room = $this->findManagedRoom((int) $params['id']);

        $this->dialog()
            ->question('Warning!', 'Are you sure you want to delete '.e($room->display_name).'?')
            ->confirm('Yes, delete', 'deleteRoom', $room->id)
            ->cancel('Cancel')
            ->send();
    }

    public function deleteRoom(int $id): void
    {
        $this->ensureCanManage('rooms.delete');

        $room = $this->findManagedRoom($id);
        $room->delete();

        $this->toast()->success('Deleted', 'Room moved to trash.')->send();
        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    public function confirmRestoreRoom(array $params): void
    {
        $this->ensureCanManage('rooms.restore');

        $room = $this->findManagedRoom((int) $params['id'], true);

        $this->dialog()
            ->question('Restore?', 'Are you sure you want to restore '.e($room->display_name).'?')
            ->confirm('Yes, restore', 'restoreRoom', $room->id)
            ->cancel('Cancel')
            ->send();
    }

    public function restoreRoom(int $id): void
    {
        $this->ensureCanManage('rooms.restore');

        $room = $this->findManagedRoom($id, true);
        $room->restore();

        $this->toast()->success('Restored', 'Room has been restored.')->send();
        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    protected function departmentId(): int
    {
        $departmentId = Auth::user()?->employeeProfile?->department_id;

        abort_unless(filled($departmentId), 403);

        return (int) $departmentId;
    }

    protected function findManagedRoom(int $id, bool $includeTrashed = false): Room
    {
        $query = Room::query()
            ->where('id', $id)
            ->where('department_id', $this->departmentId());

        if ($includeTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }
}
