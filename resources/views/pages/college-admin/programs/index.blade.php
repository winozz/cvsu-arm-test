<?php

use App\Livewire\Forms\Admin\ProgramForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Support\ProgramDuplicateDetector;
use App\Traits\CanManage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public College $college;

    public Campus $campus;

    public ProgramForm $programForm;

    public bool $programModal = false;

    public bool $isEditingProgram = false;

    public int $sharedProgramCollegeCount = 0;

    public ?string $programDuplicateConflictType = null;

    public array $programExactDuplicateConflicts = [];

    public array $programSimilarDuplicateConflicts = [];

    public bool $programSimilarityConfirmed = false;

    public function mount(): void
    {
        $this->ensureCanManage('programs.view');

        $user = auth()
            ->user()
            ?->loadMissing(['employeeProfile.campus', 'employeeProfile.college']);
        $profile = $user?->employeeProfile;

        if ($profile?->campus && $profile?->college) {
            abort_unless((int) $profile->college->campus_id === (int) $profile->campus->id, 403);

            $this->campus = $profile->campus;
            $this->college = $profile->college;

            return;
        }

        abort_unless($user?->hasRole('superAdmin'), 403);

        $this->resolveFallbackCollegeContext();
    }

    public function openCreateProgramModal(): void
    {
        $this->ensureCanManage('programs.create');

        $this->resetValidation();
        $this->programForm->resetForm();
        $this->isEditingProgram = false;
        $this->sharedProgramCollegeCount = 0;
        $this->resetProgramDuplicateState();
        $this->programModal = true;
    }

    #[On('openEditProgramModal')]
    public function openEditProgramModal(Program $program): void
    {
        $this->ensureCanManage('programs.update');

        $program = $this->findManagedProgram($program->id);

        $this->resetValidation();
        $this->programForm->setProgram($program);
        $this->isEditingProgram = true;
        $this->sharedProgramCollegeCount = $program->colleges()->count();
        $this->resetProgramDuplicateState();
        $this->programModal = true;
    }

    public function closeProgramModal(): void
    {
        $this->programModal = false;
        $this->isEditingProgram = false;
        $this->sharedProgramCollegeCount = 0;
        $this->resetProgramDuplicateState();
        $this->resetValidation();
        $this->programForm->resetForm();
    }

    public function reopenProgramModal(): void
    {
        $this->programModal = true;
    }

    public function confirmSaveProgram(): void
    {
        $this->ensureCanManage($this->isEditingProgram ? 'programs.update' : 'programs.create');

        $this->programForm->validateForm();

        $this->programModal = false;

        if (!$this->isEditingProgram) {
            $conflicts = ProgramDuplicateDetector::findConflicts(null, $this->programForm->code, $this->programForm->title);

            if ($conflicts['exact'] !== []) {
                $this->openExactProgramDuplicateDialog($conflicts['exact'], $conflicts['similar']);

                return;
            }

            if ($conflicts['similar'] !== []) {
                $this->openSimilarProgramDuplicateDialog($conflicts['similar']);

                return;
            }

            $this->resetProgramDuplicateState();
        }

        if ($this->isEditingProgram && $this->sharedProgramCollegeCount > 1) {
            $this->dialog()
                ->warning('Shared Program Update', 'This program is currently assigned to ' . $this->sharedProgramCollegeCount . ' colleges. Saving changes here will update the shared record for all assigned colleges.')
                ->confirm('Continue', 'saveProgram')
                ->cancel('Go Back', 'reopenProgramModal')
                ->send();

            return;
        }

        $title = $this->isEditingProgram ? 'Save Changes?' : 'Create Program?';
        $description = $this->isEditingProgram ? 'Are you sure you want to update this shared program?' : 'Are you sure you want to create this program and assign it to your college?';
        $confirm = $this->isEditingProgram ? 'Yes, save changes' : 'Yes, create program';

        $this->dialog()->question($title, $description)->confirm($confirm, 'saveProgram')->cancel('Cancel', 'reopenProgramModal')->send();
    }

    public function proceedWithSimilarProgramCreation(): void
    {
        $this->programSimilarityConfirmed = true;

        $this->saveProgram();
    }

    public function saveProgram(): void
    {
        $this->ensureCanManage($this->isEditingProgram ? 'programs.update' : 'programs.create');

        try {
            if (!$this->isEditingProgram) {
                $conflicts = ProgramDuplicateDetector::findConflicts(null, $this->programForm->code, $this->programForm->title);

                if ($conflicts['exact'] !== []) {
                    $this->programModal = false;
                    $this->openExactProgramDuplicateDialog($conflicts['exact'], $conflicts['similar']);

                    return;
                }

                if (!$this->programSimilarityConfirmed && $conflicts['similar'] !== []) {
                    $this->programModal = false;
                    $this->openSimilarProgramDuplicateDialog($conflicts['similar']);

                    return;
                }

                $validated = $this->programForm->validateForm();
                $program = Program::create($this->programForm->payload($validated));
                $this->college->programs()->syncWithoutDetaching([$program->id]);
                $message = 'Program created successfully.';
            } else {
                $validated = $this->programForm->validateForm();
                $this->programForm->program->update($this->programForm->payload($validated));
                $message = 'Program details updated successfully.';
            }

            $this->closeProgramModal();
            $this->dispatch('pg:eventRefresh-programsTable');
            $this->toast()->success('Success', $message)->send();
        } catch (ValidationException $e) {
            $this->reopenProgramModal();
            throw $e;
        } catch (Throwable $e) {
            $this->reopenProgramModal();
            Log::error('Program Save Failed: ' . $e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the program.')->send();
        }
    }

    protected function resolveFallbackCollegeContext(): void
    {
        $fallbackCollege = College::query()->with('campus')->orderBy('name')->first();

        abort_unless($fallbackCollege?->campus, 403);

        $this->campus = $fallbackCollege->campus;
        $this->college = $fallbackCollege;
    }

    protected function resetProgramDuplicateState(): void
    {
        $this->programDuplicateConflictType = null;
        $this->programExactDuplicateConflicts = [];
        $this->programSimilarDuplicateConflicts = [];
        $this->programSimilarityConfirmed = false;
    }

    protected function openExactProgramDuplicateDialog(array $exactConflicts, array $similarConflicts = []): void
    {
        $this->programDuplicateConflictType = 'exact';
        $this->programExactDuplicateConflicts = $exactConflicts;
        $this->programSimilarDuplicateConflicts = $similarConflicts;
        $this->programSimilarityConfirmed = false;

        $this->dialog()->warning('Exact Duplicate Program Found', ProgramDuplicateDetector::exactWarningMessage($exactConflicts, $similarConflicts))->confirm('Go Back', 'reopenProgramModal')->send();
    }

    protected function openSimilarProgramDuplicateDialog(array $similarConflicts): void
    {
        $this->programDuplicateConflictType = 'similar';
        $this->programExactDuplicateConflicts = [];
        $this->programSimilarDuplicateConflicts = $similarConflicts;
        $this->programSimilarityConfirmed = false;

        $this->dialog()->warning('Possible Duplicate Program', ProgramDuplicateDetector::similarWarningMessage($similarConflicts))->confirm('Proceed anyway', 'proceedWithSimilarProgramCreation')->cancel('Go Back', 'reopenProgramModal')->send();
    }

    protected function findManagedProgram(int $id, bool $includeTrashed = false): Program
    {
        $query = Program::query()->whereKey($id)->whereHas('colleges', fn($query) => $query->whereKey($this->college->id));

        if ($includeTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }
};
?>

<div>
    <div
        class="flex flex-col items-start justify-between gap-4 p-6 mb-6 bg-white rounded-lg shadow md:flex-row md:items-center dark:bg-gray-800">
        <div>
            <h3 class="text-xl font-medium dark:text-white">{{ $college->code }}</h3>
            <p class="italic text-zinc-600 dark:text-zinc-200">{{ $college->name }}</p>

            <div class="mt-2">
                <span
                    class="px-2 py-1 text-xs font-semibold rounded-full {{ $college->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $college->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
        </div>
        <div class="flex gap-2">
            @can('programs.view')
                <x-button tag="a" href="{{ route('college-admin.dashboard') }}" sm outline text="Back to Dashboard" />
            @endcan
        </div>
    </div>

    <div
        class="flex flex-col items-start justify-between gap-4 px-6 py-4 mb-6 bg-white rounded-lg shadow md:flex-row md:items-center dark:bg-gray-800">
        <h1 class="text-2xl font-bold dark:text-white">Program List</h1>
        <div class="flex gap-2">
            @can('programs.create')
                <x-button wire:click="openCreateProgramModal" sm color="primary" icon="plus" text="New Program" />
            @endcan
        </div>
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.programs-table :college-id="$college->id" />
    </div>

    <x-modal wire="programModal" title="{{ $isEditingProgram ? 'Edit Program Details' : 'New Program' }}"
        size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Program Code" wire:model="programForm.code"
                    hint="Use a short code like BSCS or BSEd." />
                <x-input label="Program Title" wire:model="programForm.title" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Number of Years" type="number" min="1" wire:model="programForm.no_of_years" />

                <x-select.styled label="Level" wire:model="programForm.level" :options="collect(Program::LEVELS)
                    ->map(fn($label, $value) => ['label' => $label, 'value' => $value])
                    ->values()
                    ->toArray()"
                    select="label:label|value:value" />
            </div>

            <x-textarea label="Description" wire:model="programForm.description"
                hint="Optional short description for this program." />

            @if ($isEditingProgram && $sharedProgramCollegeCount > 1)
                <div
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                    This is a shared program assigned to <strong>{{ $sharedProgramCollegeCount }}</strong> colleges.
                    Editing it here will update the shared record for all attached colleges.
                </div>
            @endif

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="programForm.is_active" label="Program is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $programForm->is_active ? 'This program is available for active assignments.' : 'This program will be marked inactive.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            @canany(['programs.update', 'programs.create'])
                <x-button flat text="Cancel" wire:click="closeProgramModal" sm />
                <x-button color="primary" :text="$isEditingProgram ? 'Save Changes' : 'Create Program'" wire:click="confirmSaveProgram" sm />
            @endcanany
        </x-slot:footer>
    </x-modal>
</div>
