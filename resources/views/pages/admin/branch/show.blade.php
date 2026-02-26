<?php

use Livewire\Component;
use App\Models\Branch;
use App\Livewire\Forms\BranchForm;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;

    public Branch $branch;
    public BranchForm $form;
    public bool $editModal = false;

    public function mount(Branch $branch): void
    {
        $this->branch = $branch;
        $this->form->setBranch($branch);
    }

    public function edit(): void
    {
        // Re-hydrate the form just in case and open the modal
        $this->form->setBranch($this->branch);
        $this->editModal = true;
    }

    public function save(): void
    {
        $this->form->store();
        $this->branch->refresh();
        $this->editModal = false;
        $this->toast()->success('Success', 'Branch updated successfully.')->send();
    }
};
?>

<div class="max-w-7xl mx-auto py-8">
    <x-toast />

    {{-- Header & Campus Information --}}
    <div
        class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-lg shadow dark:bg-gray-800">
        <div>
            <h1 class="text-2xl font-bold dark:text-white">{{ $branch->code }}</h1>
            <p class="italic text-zinc-600 dark:text-zinc-200">{{ $branch->name }} </p>
            <p class="text-gray-600 dark:text-gray-300 mt-1">{{ $branch->type }} Campus | {{ $branch->address }}</p>
            <div class="mt-2">
                <span
                    class="px-2 py-1 text-xs font-semibold rounded-full {{ $branch->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $branch->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
        <div class="flex gap-2">
            <x-button wire:click="edit" sm color="primary" icon="pencil" text="Edit Details" />
            <x-button tag="a" href="{{ route('admin.branches') }}" sm outline text="Back to List" />
        </div>
    </div>

    {{-- Departments Section --}}
    <div class="mt-8">
        <h2 class="text-xl font-semibold mb-4 dark:text-white">Departments</h2>

        <div class="bg-white p-6 rounded-lg shadow dark:bg-gray-800">
            {{-- You will replace this with your actual Departments PowerGrid Table later --}}
            <p class="text-gray-500 dark:text-gray-400">
                Departments under this campus will be listed here.
            </p>
            {{-- Example usage for later:
            <livewire:admin.departments-table :branch-id="$branch->id" /> --}}

            <livewire:admin.branch-departments-table :branch-id="$branch->id" />

        </div>
    </div>

    {{-- Edit Modal --}}
    <x-modal wire="editModal" title="Edit {{ $branch->name }}">
        <div class="space-y-4">
            <x-input label="Code" wire:model="form.code" />
            <x-input label="Name" wire:model="form.name" />
            <x-select.styled label="Campus Type" wire:model="form.type" :options="['Main', 'Satellite']" />
            <x-textarea label="Address" wire:model="form.address" />
            <x-toggle label="Active" wire:model="form.is_active" />
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('editModal', false)" />
            <x-button color="primary" text="Save Changes" wire:click="save" />
        </x-slot:footer>
    </x-modal>
</div>