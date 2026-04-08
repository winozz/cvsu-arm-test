<?php

use App\Livewire\Forms\Admin\PermissionsForm;
use App\Models\Permission;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

    public PermissionsForm $form;

    public bool $permissionModal = false;

    public bool $isEditing = false;

    public function openCreateModal()
    {
        $this->form->resetForm();
        $this->isEditing = false;
        $this->permissionModal = true;
    }

    #[On('editPermission')]
    public function openEditModal(Permission $permission)
    {
        $this->form->setPermission($permission);
        $this->isEditing = true;
        $this->permissionModal = true;
    }

    public function save(): void
    {
        try {
            if ($this->isEditing) {
                $this->form->update();
                $message = 'Permission updated successfully.';
            } else {
                $this->form->store();
                $message = 'Permission created successfully.';
            }

            $this->permissionModal = false;
            $this->dispatch('pg:eventRefresh-permissionsTable');
            $this->toast()->success('Success', $message)->send();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Permission Save Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the permission.')->send();
        }
    }
};
?>

<div class="py-8">
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-xl font-bold dark:text-white">Permissions</h1>
        <div class="flex gap-2">
            <x-button wire:click="openCreateModal" sm color="primary" icon="plus" text="New Permission" />
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.permissions-table />
    </div>

    <x-modal wire="permissionModal" title="{{ $isEditing ? 'Edit Permission' : 'New Permission' }}">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Name" wire:model="form.name" hint="Use the system permission key, e.g. users.view or campuses.view" />
                <x-input label="Guard" wire:model="form.guard_name" />
            </div>
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('permissionModal', false)" sm />
            <x-button color="primary" :text="$isEditing ? 'Save Changes' : 'Save Permission'" wire:click="save" sm />
        </x-slot:footer>
    </x-modal>
</div>
