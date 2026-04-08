<?php

use App\Livewire\Forms\Admin\RolesForm;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

    public RolesForm $form;

    public bool $roleModal = false;

    public bool $isEditing = false;

    public Collection $availablePermissions;

    public function mount(): void
    {
        $this->availablePermissions = Permission::query()->orderBy('name')->get();
    }

    public function openCreateModal(): void
    {
        $this->form->resetForm();
        $this->isEditing = false;
        $this->roleModal = true;
    }

    #[On('openEditModal')]
    public function openEditModal(Role $role): void
    {
        $this->form->setRole($role->load('permissions'));
        $this->isEditing = true;
        $this->roleModal = true;
    }

    public function save(): void
    {
        try {
            if ($this->isEditing) {
                $this->form->update();
                $message = 'Role updated successfully.';
            } else {
                $this->form->store();
                $message = 'Role created successfully.';
            }

            $this->roleModal = false;
            $this->dispatch('pg:eventRefresh-rolesTable');
            $this->toast()->success('Success', $message)->send();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Role Save Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the role.')->send();
        }
    }
};
?>

<div class="py-8">
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-xl font-bold dark:text-white">Roles</h1>
        <div class="flex gap-2">
            <x-button wire:click="openCreateModal" sm color="primary" icon="plus" text="New Role" />
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.roles-table />
    </div>

    <x-modal wire="roleModal" title="{{ $isEditing ? 'Edit Role' : 'New Role' }}">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Name" wire:model="form.name" hint="Use the system role key, e.g. collegeAdmin" />
                <x-input label="Guard" wire:model="form.guard_name" />
            </div>

            <x-select.styled label="Assign Permissions" wire:model="form.permissions"
                hint="Select one or more permissions for this role" placeholder="Choose permissions" multiple searchable
                :options="$availablePermissions
                    ->map(
                        fn($permission) => [
                            'label' => Str::headline($permission->name),
                            'value' => (string) $permission->id,
                        ],
                    )
                    ->toArray()" select="label:label|value:value" />
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('roleModal', false)" sm />
            <x-button color="primary" :text="$isEditing ? 'Save Changes' : 'Save Role'" wire:click="save" sm />
        </x-slot:footer>
    </x-modal>
</div>
