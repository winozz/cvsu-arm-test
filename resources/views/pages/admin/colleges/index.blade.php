<?php

use App\Livewire\Forms\Admin\CampusForm;
use App\Livewire\Forms\Admin\CollegeForm;
use App\Models\Campus;
use App\Models\College;
use App\Traits\CanManage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public Campus $campus;

    public CampusForm $form;

    public CollegeForm $collegeForm;

    public bool $campusModal = false;

    public bool $createCollegeModal = false;

    public function mount(Campus $campus): void
    {
        $this->ensureCanManage('colleges.view');

        $this->campus = $campus;
        $this->form->setCampus($campus);
        $this->collegeForm->resetForm();
    }

    #[Computed]
    public function collegeStats(): array
    {
        return [
            'total' => College::query()->where('campus_id', $this->campus->id)->count(),
            'active' => College::query()->where('campus_id', $this->campus->id)->where('is_active', true)->count(),
            'inactive' => College::query()->where('campus_id', $this->campus->id)->where('is_active', false)->count(),
        ];
    }

    public function openCreateCollegeModal(): void
    {
        $this->ensureCanManage('colleges.create');
        $this->resetValidation();
        $this->collegeForm->resetForm();
        $this->collegeForm->campusId = $this->campus->id;
        $this->createCollegeModal = true;
    }

    public function reopenCreateCollegeModal(): void
    {
        $this->createCollegeModal = true;
    }

    public function closeCreateCollegeModal(): void
    {
        $this->createCollegeModal = false;
        $this->resetValidation();
        $this->collegeForm->resetForm();
    }

    public function confirmCreateCollege(): void
    {
        $this->ensureCanManage('colleges.create');
        $this->collegeForm->validateForm();
        $this->createCollegeModal = false;

        $this->dialog()->question('Create College?', 'Are you sure you want to add this new college?')->confirm('Yes, create', 'createCollege')->cancel('Cancel', 'reopenCreateCollegeModal')->send();
    }

    public function createCollege(): void
    {
        $this->ensureCanManage('colleges.create');

        try {
            $validated = $this->collegeForm->validateForm();
            $payload = array_merge($this->collegeForm->payload($validated), ['campus_id' => $this->campus->id]);
            College::query()->create($payload);
            $this->createCollegeModal = false;
            $this->collegeForm->resetForm();
            $this->dispatch('pg:eventRefresh-collegesTable');
            $this->toast()->success('Success', 'College created successfully.')->send();
        } catch (ValidationException $e) {
            $this->reopenCreateCollegeModal();
            throw $e;
        } catch (Exception $e) {
            $this->reopenCreateCollegeModal();
            Log::error('College creation failed: ' . $e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while creating the college.')->send();
        }
    }

    public function editCampus(): void
    {
        $this->ensureCanManage('campuses.update');

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
        $this->ensureCanManage('campuses.update');

        $this->form->validateForm();
        $this->campusModal = false;

        $this->dialog()->question('Save Changes?', 'Are you sure you want to update this campus?')->confirm('Yes, save changes', 'saveCampus')->cancel('Cancel', 'reopenCampusModal')->send();
    }

    public function saveCampus(): void
    {
        $this->ensureCanManage('campuses.update');

        try {
            $validated = $this->form->validateForm();
            $this->campus->update($this->form->payload($validated));
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

<div class="space-y-6">
    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
        <a href="{{ route('campuses.index') }}" class="hover:text-primary-600 dark:hover:text-primary-400">Campuses</a>
        <span>/</span>
        <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $campus->code }}</span>
    </nav>

    {{-- Campus Info Card --}}
    <div class="flex flex-col items-start justify-between gap-4  md:flex-row md:items-center">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold dark:text-white">{{ $campus->code }}</h1>
                <x-badge :text="$campus->is_active ? 'Active' : 'Inactive'" :color="$campus->is_active ? 'emerald' : 'red'" light round />
            </div>
            <p class="italic text-zinc-600 dark:text-zinc-200">{{ $campus->name }}</p>
        </div>

        <div class="flex gap-2">
            @can('campuses.update')
                <x-button wire:click="editCampus" sm color="primary" icon="pencil" text="Edit Details" />
            @endcan
            @can('campuses.view')
                <x-button tag="a" href="{{ route('campuses.index') }}" sm outline text="Back to Campuses" />
            @endcan
        </div>
    </div>

    {{-- College Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Colleges</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->collegeStats['total'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active</p>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->collegeStats['active'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Inactive</p>
            <p class="mt-1 text-2xl font-bold text-red-500">{{ $this->collegeStats['inactive'] }}</p>
        </x-card>
    </div>

    {{-- Main College Body --}}
    <x-card>
        {{-- College List Header --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between pb-4 border-b border-zinc-200">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold dark:text-white">College List</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Colleges under {{ $campus->name }}.</p>
            </div>

            @can('colleges.create')
                <x-button wire:click="openCreateCollegeModal" sm color="primary" icon="plus" text="New College" />
            @endcan
        </div>

        {{-- Colleges Table --}}
        <div class="p-6">
            <livewire:tables.admin.colleges-table :campus-id="$campus->id" />
        </div>
    </x-card>

    {{-- Edit Campus Modal --}}
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
            @can('campuses.update')
                <x-button flat text="Cancel" wire:click="closeCampusModal" sm />
                <x-button color="primary" text="Save Changes" wire:click="confirmSaveCampus" sm />
            @endcan
        </x-slot:footer>
    </x-modal>

    {{-- Create College Modal --}}
    <x-modal wire="createCollegeModal" title="Add New College" size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="College Code" wire:model="collegeForm.code" hint="Use a short code like CEIT or CAS." />
                <x-input label="College Name" wire:model="collegeForm.name" />
            </div>

            <x-input label="Campus" :value="$campus->code . ' - ' . $campus->name" disabled
                hint="This college will be created under the current campus." />

            <x-textarea label="Description" wire:model="collegeForm.description"
                hint="Optional short description for this college." />

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="collegeForm.is_active" label="College is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $collegeForm->is_active ? 'This college will be available for active assignments.' : 'This college will be marked as inactive.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            @can('colleges.create')
                <x-button flat text="Cancel" wire:click="closeCreateCollegeModal" sm />
                <x-button color="primary" text="Create College" wire:click="confirmCreateCollege" sm />
            @endcan
        </x-slot:footer>
    </x-modal>

</div>
