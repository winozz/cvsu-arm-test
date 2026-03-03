<?php

use App\Imports\BranchesImport;
use App\Livewire\Forms\Admin\BranchForm;
use App\Models\Branch;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use Interactions, WithFileUploads;

    public BranchForm $form;

    public bool $branchModal = false;

    public bool $importModal = false;

    public $importFile;

    public function create(): void
    {
        $this->form->reset();
        $this->branchModal = true;
    }

    public function save(): void
    {
        $this->form->store();
        $this->branchModal = false;
        $this->toast()->success('Success', 'Branch saved successfully.')->send();
        $this->dispatch('pg:eventRefresh-branchTable');
    }

    #[On('confirmDelete')]
    public function confirmDelete($id): void
    {
        $branchId = is_array($id) ? $id['id'] : $id;
        $this->dialog()->question('Warning!', 'Are you sure you want to delete this branch?')->confirm('Yes, delete', 'delete', $branchId)->cancel('Cancel')->send();
    }

    public function delete($id): void
    {
        Branch::findOrFail($id)->delete();
        $this->toast()->success('Deleted', 'Branch moved to trash.')->send();
        $this->dispatch('pg:eventRefresh-branchTable');
    }

    #[On('confirmRestore')]
    public function confirmRestore($id): void
    {
        $branchId = is_array($id) ? $id['id'] : $id;
        $this->dialog()->question('Restore?', 'Are you sure you want to restore this branch?')->confirm('Yes, restore', 'restore', $branchId)->cancel('Cancel')->send();
    }

    public function restore($id): void
    {
        Branch::withTrashed()->findOrFail($id)->restore();
        $this->toast()->success('Restored', 'Branch has been restored.')->send();
        $this->dispatch('pg:eventRefresh-branchTable');
    }

    public function importData(): void
    {
        $this->validate([
            'importFile' => 'required|mimes:csv,txt,xlsx,xls',
        ]);

        Excel::import(new BranchesImport(), $this->importFile);

        $this->importModal = false;
        $this->reset('importFile');
        $this->toast()->success('Import Complete', 'Data has been imported successfully.')->send();
        $this->dispatch('pg:eventRefresh-branchTable');
    }
};
?>

<div class="max-w-7xl mx-auto py-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <h1 class="text-xl font-bold dark:text-white">Branches/Colleges Management</h1>

        <div class="flex gap-2">
            <x-button wire:click="$set('importModal', true)" sm outline icon="arrow-up-tray" text="Import Data" />
            <x-button wire:click="create" sm color="primary" icon="plus" text="New Branch" />
        </div>
    </div>

    {{-- PowerGrid Table --}}
    <livewire:admin.branches-table />

    {{-- Add Modal --}}
    <x-modal wire="branchModal" title="New Branch">
        <div class="space-y-4">
            <x-input label="Code" wire:model="form.code" />
            <x-input label="Name" wire:model="form.name" />
            <x-select.styled label="Campus Type" wire:model="form.type" :options="['Main', 'Satellite']" />
            <x-textarea label="Address" wire:model="form.address" />
            <x-toggle label="Active" wire:model="form.is_active" />
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('branchModal', false)" sm />
            <x-button color="primary" text="Save" wire:click="save" sm />
        </x-slot:footer>
    </x-modal>

    {{-- Import Modal --}}
    <x-modal wire="importModal" title="Import Branches">
        <div class="space-y-4">
            <x-upload wire:model="importFile" label="Select Excel/CSV File" hint="Supported files: .xlsx, .csv" />
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="$set('importModal', false)" sm />
            <x-button color="primary" text="Start Import" wire:click="importData" sm />
        </x-slot:footer>
    </x-modal>
</div>
