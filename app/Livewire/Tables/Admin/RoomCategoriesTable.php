<?php

namespace App\Livewire\Tables\Admin;

use App\Models\Room;
use App\Models\RoomCategory;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Livewire\Attributes\On;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use TallStackUi\Traits\Interactions;

final class RoomCategoriesTable extends PowerGridComponent
{
    use CanManage, Interactions, WithExport;

    public string $tableName = 'roomCategoriesTable';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable(fileName: 'room-categories-list')
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
        return RoomCategory::query()
            ->when($this->softDeletes === 'withTrashed', fn ($query) => $query->withTrashed())
            ->when($this->softDeletes === 'onlyTrashed', fn ($query) => $query->onlyTrashed());
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('name')
            ->add('slug')
            ->add('status_badge', fn (RoomCategory $roomCategory) => $this->badge($roomCategory->is_active ? 'Active' : 'Inactive', $roomCategory->is_active ? 'primary' : 'red'))
            ->add('rooms_count', fn (RoomCategory $roomCategory) => (string) Room::withTrashed()->where('room_category_id', $roomCategory->id)->count());
    }

    public function columns(): array
    {
        return [
            Column::make('Id', 'id')->hidden(isHidden: true, isForceHidden: false),
            Column::make('Name', 'name')->sortable()->searchable(),
            Column::make('Slug', 'slug')->sortable()->searchable(),
            Column::make('Status', 'status_badge', 'is_active')->sortable(),
            Column::make('Rooms', 'rooms_count'),
            Column::action('Action'),
        ];
    }

    public function filters(): array
    {
        return [
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

    public function actions($row): array
    {
        $actions = [];

        if (! $row->trashed()) {
            if ($this->canManage('room_categories.update')) {
                $actions[] = Button::add('edit-room-category')
                    ->slot('Edit')
                    ->icon('default-pencil-square', ['class' => 'w-4 h-4 text-blue-500 group-hover:text-blue-700'])
                    ->class('group flex items-center gap-1 rounded border border-blue-500 px-2 py-1 text-xs text-blue-500 transition-all duration-300 hover:bg-zinc-100 hover:text-blue-700 cursor-pointer')
                    ->dispatch('editRoomCategory', ['roomCategory' => $row->id]);
            }

            if ($this->canManage('room_categories.delete')) {
                $actions[] = Button::add('delete-room-category')
                    ->slot('Remove')
                    ->icon('default-trash', ['class' => 'w-4 h-4 text-red-500 group-hover:text-red-700'])
                    ->class('group flex items-center gap-1 rounded border border-red-500 px-2 py-1 text-xs text-red-500 transition-all duration-300 hover:bg-zinc-100 hover:text-red-700 cursor-pointer')
                    ->call('confirmDeleteRoomCategory', ['id' => $row->id]);
            }
        } elseif ($this->canManage('room_categories.restore')) {
            $actions[] = Button::add('restore-room-category')
                ->slot('Restore')
                ->icon('default-arrow-path', ['class' => 'w-4 h-4 text-amber-500 group-hover:text-amber-700'])
                ->class('group flex items-center gap-1 rounded border border-amber-500 px-2 py-1 text-xs text-amber-500 transition-all duration-300 hover:bg-zinc-100 hover:text-amber-700 cursor-pointer')
                ->call('confirmRestoreRoomCategory', ['id' => $row->id]);
        }

        return $actions;
    }

    #[On('confirmDeleteRoomCategory')]
    public function confirmDeleteRoomCategory(array $params): void
    {
        $this->ensureCanManage('room_categories.delete');

        $roomCategory = RoomCategory::query()->findOrFail((int) $params['id']);

        $this->dialog()
            ->question('Warning!', 'Are you sure you want to delete '.e($roomCategory->name).'?')
            ->confirm('Yes, delete', 'deleteRoomCategory', $roomCategory->id)
            ->cancel('Cancel')
            ->send();
    }

    #[On('deleteRoomCategory')]
    public function deleteRoomCategory(int $id): void
    {
        $this->ensureCanManage('room_categories.delete');

        $roomCategory = RoomCategory::query()->findOrFail($id);

        if (Room::withTrashed()->where('room_category_id', $roomCategory->id)->exists()) {
            $this->toast()->error('Unable to delete', 'Room categories assigned to rooms cannot be deleted.')->send();

            return;
        }

        $roomCategory->delete();

        $this->toast()->success('Deleted', 'Room category moved to trash.')->send();
        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    #[On('confirmRestoreRoomCategory')]
    public function confirmRestoreRoomCategory(array $params): void
    {
        $this->ensureCanManage('room_categories.restore');

        $roomCategory = RoomCategory::withTrashed()->findOrFail((int) $params['id']);

        $this->dialog()
            ->question('Restore?', 'Are you sure you want to restore '.e($roomCategory->name).'?')
            ->confirm('Yes, restore', 'restoreRoomCategory', $roomCategory->id)
            ->cancel('Cancel')
            ->send();
    }

    #[On('restoreRoomCategory')]
    public function restoreRoomCategory(int $id): void
    {
        $this->ensureCanManage('room_categories.restore');

        RoomCategory::withTrashed()->findOrFail($id)->restore();

        $this->toast()->success('Restored', 'Room category has been restored.')->send();
        $this->dispatch('pg:eventRefresh-'.$this->tableName);
    }

    protected function badge(string $label, string $color): string
    {
        return trim(Blade::render(
            '<x-badge :text="$text" :color="$color" round xs />',
            ['text' => $label, 'color' => $color]
        ));
    }
}
