<?php

use App\Livewire\Forms\Admin\CampusForm;
use App\Models\Campus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use Interactions;

    public Campus $campus;

    public CampusForm $form;

    public bool $campusModal = false;

    public function mount(Campus $campus): void
    {
        $this->campus = $campus;
        $this->form->setCampus($campus);
    }

    public function editCampus(): void
    {
        $this->resetValidation();
        $this->form->setCampus($this->campus->fresh());
        $this->campusModal = true;
    }

    public function closeCampusModal(): void
    {
        $this->campusModal = false;
        $this->resetValidation();
        $this->form->setCampus($this->campus->fresh());
    }

    public function reopenCampusModal(): void
    {
        $this->campusModal = true;
    }

    public function confirmSaveCampus(): void
    {
        $this->form->validateForm();
        $this->campusModal = false;

        $this->dialog()->question('Save Changes?', 'Are you sure you want to update this campus?')->confirm('Yes, save changes', 'saveCampus')->cancel('Cancel', 'reopenCampusModal')->send();
    }

    public function saveCampus(): void
    {
        try {
            $this->form->update();
            $this->campus->refresh();

            $this->campusModal = false;
            $this->toast()->success('Success', 'Campus details updated successfully.')->send();
        } catch (ValidationException $e) {
            $this->reopenCampusModal();
            throw $e;
        } catch (Exception $e) {
            $this->reopenCampusModal();
            Log::error('Campus Save Failed: ' . $e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the campus.')->send();
        }
    }
};
?>

<div>
    <div
        class="flex flex-col items-start justify-between gap-4 p-6 mb-6 bg-white rounded-lg shadow md:flex-row md:items-center dark:bg-gray-800">
        <div>
            <h1 class="text-xl font-bold dark:text-white">{{ $campus->code }}</h1>
            <p class="italic text-zinc-600 dark:text-zinc-200">{{ $campus->name }}</p>

            <div class="mt-2">
                <span
                    class="px-2 py-1 text-xs font-semibold rounded-full {{ $campus->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $campus->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>

        <div class="flex gap-2">
            <x-button wire:click="editCampus" sm color="primary" icon="pencil" text="Edit Details" />
            <x-button tag="a" href="{{ route('admin.campuses') }}" sm outline text="Back to Campuses" />
        </div>
    </div>
    <div
        class="flex flex-col items-start justify-between gap-4 px-6 py-4 mb-6 bg-white rounded-lg shadow md:flex-row md:items-center dark:bg-gray-800">
        <h1 class="text-2xl font-bold dark:text-white">College List</h1>
    </div>
    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.colleges-table :campus-id="$campus->id" />
    </div>

    <x-modal wire="campusModal" title="Edit Campus Details" size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Campus Code" wire:model="form.code" hint="Use a short code like CvSU-MAIN." />
                <x-input label="Campus Name" wire:model="form.name" />
            </div>

            <x-textarea label="Description" wire:model="form.description"
                hint="Optional short description for this campus." />

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="form.is_active" label="Campus is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $form->is_active ? 'This campus is available for active assignments.' : 'This campus will be marked inactive.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="closeCampusModal" sm />
            <x-button color="primary" text="Save Changes" wire:click="confirmSaveCampus" sm />
        </x-slot:footer>
    </x-modal>
</div>
