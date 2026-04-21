<?php

use App\Livewire\Forms\Admin\PermissionsForm;
use App\Models\Permission;
use App\Traits\CanManage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public function mount(): void
    {
        $this->ensureCanManage('permissions.view');
    }

    public PermissionsForm $form;

    public bool $permissionModal = false;

    public bool $isEditing = false;

    public function openCreateModal()
    {
        $this->ensureCanManage('permissions.create');

        $this->form->resetForm();
        $this->isEditing = false;
        $this->permissionModal = true;
    }

    #[On('editPermission')]
    public function openEditModal(Permission $permission)
    {
        $this->ensureCanManage('permissions.update');

        $this->form->setPermission($permission);
        $this->isEditing = true;
        $this->permissionModal = true;
    }

    public function save(): void
    {
        $this->ensureCanManage($this->isEditing ? 'permissions.update' : 'permissions.create');

        try {
            $validated = $this->form->validateForm();

            if ($this->isEditing) {
                $this->form->permission->update([
                    'name' => $validated['name'],
                    'guard_name' => $validated['guard_name'],
                ]);
                $this->permissionModal = false;
                $this->dispatch('pg:eventRefresh-permissionsTable');
                $this->toast()->success('Success', 'Permission updated successfully.')->send();

                return;
            }

            Permission::create([
                'name' => $validated['name'],
                'guard_name' => $validated['guard_name'],
            ]);
            $this->permissionModal = false;
            $this->dispatch('pg:eventRefresh-permissionsTable');
            $this->toast()->success('Success', 'Permission created successfully.')->send();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Permission Save Failed: ' . $e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the permission.')->send();
        }
    }
};
?>

<div class="py-8">
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-xl font-bold dark:text-white">Permissions</h1>
        <div class="flex gap-2">
            @can('permissions.create')
                <x-button wire:click="openCreateModal" sm color="primary" icon="plus" text="New Permission" />
            @endcan
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.permissions-table />
    </div>

    <x-modal wire="permissionModal" title="{{ $isEditing ? 'Edit Permission' : 'New Permission' }}">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Name" wire:model="form.name"
                    hint="Use the system permission key, e.g. users.view or campuses.view" />
                <x-input label="Guard" wire:model="form.guard_name" />
            </div>
        </div>

        <x-slot:footer>
            @canany(['permissions.update', 'permissions.create'])
                <x-button flat text="Cancel" wire:click="$set('permissionModal', false)" sm />
                <x-button color="primary" :text="$isEditing ? 'Save Changes' : 'Save Permission'" wire:click="save" sm />
            @endcanany
        </x-slot:footer>
    </x-modal>
</div>
