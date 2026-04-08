<?php

use App\Livewire\Forms\Admin\DepartmentForm;
use App\Livewire\Forms\Admin\CollegeForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use Interactions;

    public College $college;

    public Campus $campus;

    public CollegeForm $collegeForm;

    public DepartmentForm $departmentForm;

    public bool $collegeModal = false;

    public bool $departmentModal = false;

    public bool $isEditingDepartment = false;

    public bool $departmentDuplicateConflictDetected = false;

    public array $departmentDuplicateConflicts = [];

    public function mount(Campus $campus, College $college): void
    {
        abort_unless($college->campus_id === $campus->id, 404);

        $this->campus = $campus;
        $this->college = $college;
        $this->collegeForm->setCollege($college);
        $this->departmentForm->resetForm($campus->id, $college->id);
    }

    public function editCollege(): void
    {
        $this->resetValidation();
        $this->collegeForm->setCollege($this->college->fresh());
        $this->collegeModal = true;
    }

    public function closeCollegeModal(): void
    {
        $this->collegeModal = false;
        $this->resetValidation();
        $this->collegeForm->setCollege($this->college->fresh());
    }

    public function reopenCollegeModal(): void
    {
        $this->collegeModal = true;
    }

    public function confirmSaveCollege(): void
    {
        $this->collegeForm->validateForm();
        $this->collegeModal = false;

        $this->dialog()->question('Save Changes?', 'Are you sure you want to update this college?')->confirm('Yes, save changes', 'saveCollege')->cancel('Cancel', 'reopenCollegeModal')->send();
    }

    public function saveCollege(): void
    {
        try {
            $this->collegeForm->update();
            $this->college->refresh();
            $this->collegeModal = false;
            $this->toast()->success('Success', 'College details updated successfully.')->send();
        } catch (ValidationException $e) {
            $this->reopenCollegeModal();
            throw $e;
        } catch (Exception $e) {
            $this->reopenCollegeModal();
            Log::error('College Save Failed: ' . $e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the college.')->send();
        }
    }

    #[On('openEditDepartmentModal')]
    public function openEditDepartmentModal(Department $department): void
    {
        abort_unless($department->college_id === $this->college->id, 404);

        $this->resetValidation();
        $this->departmentForm->setDepartment($department);
        $this->isEditingDepartment = true;
        $this->departmentDuplicateConflictDetected = false;
        $this->departmentDuplicateConflicts = [];
        $this->departmentModal = true;
    }

    public function openCreateDepartmentModal(): void
    {
        $this->resetValidation();
        $this->departmentForm->resetForm($this->campus->id, $this->college->id);
        $this->isEditingDepartment = false;
        $this->departmentDuplicateConflictDetected = false;
        $this->departmentDuplicateConflicts = [];
        $this->departmentModal = true;
    }

    public function closeDepartmentModal(): void
    {
        $this->departmentModal = false;
        $this->isEditingDepartment = false;
        $this->departmentDuplicateConflictDetected = false;
        $this->departmentDuplicateConflicts = [];
        $this->resetValidation();
        $this->departmentForm->resetForm($this->campus->id, $this->college->id);
    }

    public function reopenDepartmentModal(): void
    {
        $this->departmentModal = true;
    }

    public function confirmSaveDepartment(): void
    {
        $this->departmentForm->validateForm();
        $this->departmentModal = false;

        if (! $this->isEditingDepartment) {
            $conflicts = $this->findPotentialDepartmentConflicts();

            if ($conflicts !== []) {
                $this->departmentDuplicateConflictDetected = true;
                $this->departmentDuplicateConflicts = $conflicts;

                $this->dialog()
                    ->warning('Possible Duplicate Department', $this->duplicateDepartmentWarningMessage($conflicts))
                    ->confirm('Proceed anyway', 'saveDepartment')
                    ->cancel('Go Back', 'reopenDepartmentModal')
                    ->send();

                return;
            }
        }

        $this->departmentDuplicateConflictDetected = false;
        $this->departmentDuplicateConflicts = [];
        $title = $this->isEditingDepartment ? 'Save Changes?' : 'Create Department?';
        $description = $this->isEditingDepartment ? 'Are you sure you want to update this department?' : 'Are you sure you want to create this department?';
        $confirm = $this->isEditingDepartment ? 'Yes, save changes' : 'Yes, create department';

        $this->dialog()->question($title, $description)->confirm($confirm, 'saveDepartment')->cancel('Cancel', 'reopenDepartmentModal')->send();
    }

    public function saveDepartment(): void
    {
        try {
            if ($this->isEditingDepartment) {
                $this->departmentForm->update();
                $message = 'Department details updated successfully.';
            } else {
                $this->departmentForm->store();
                $message = 'Department created successfully.';
            }

            $this->departmentModal = false;
            $this->isEditingDepartment = false;
            $this->departmentDuplicateConflictDetected = false;
            $this->departmentDuplicateConflicts = [];
            $this->dispatch('pg:eventRefresh-departmentsTable');
            $this->toast()->success('Success', $message)->send();
        } catch (ValidationException $e) {
            $this->reopenDepartmentModal();
            throw $e;
        } catch (Exception $e) {
            $this->reopenDepartmentModal();
            Log::error('Department Save Failed: ' . $e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the department.')->send();
        }
    }

    protected function findPotentialDepartmentConflicts(): array
    {
        return Department::query()
            ->where('college_id', $this->college->id)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (Department $department) => $this->departmentLooksSimilar($department))
            ->sortBy(fn (Department $department) => $department->code)
            ->map(fn (Department $department) => e($department->code) . ' - ' . e($department->name))
            ->values()
            ->all();
    }

    protected function departmentLooksSimilar(Department $department): bool
    {
        $enteredCode = $this->normalizeDepartmentCode($this->departmentForm->code);
        $existingCode = $this->normalizeDepartmentCode($department->code);
        $enteredName = $this->normalizeDepartmentName($this->departmentForm->name);
        $existingName = $this->normalizeDepartmentName($department->name);

        return $this->codesLookSimilar($enteredCode, $existingCode)
            || $this->namesLookSimilar($enteredName, $existingName);
    }

    protected function codesLookSimilar(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        if (str_contains($left, $right) || str_contains($right, $left)) {
            return true;
        }

        if (levenshtein($left, $right) <= 2) {
            return true;
        }

        similar_text($left, $right, $percent);

        return ($percent / 100) >= 0.8;
    }

    protected function namesLookSimilar(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        if (str_contains($left, $right) || str_contains($right, $left)) {
            return true;
        }

        similar_text($left, $right, $percent);

        return ($percent / 100) >= 0.9;
    }

    protected function normalizeDepartmentCode(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(trim((string) $value))) ?? '';
    }

    protected function normalizeDepartmentName(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::squish((string) $value))) ?? '';
    }

    protected function duplicateDepartmentWarningMessage(array $conflicts): string
    {
        $items = collect($conflicts)
            ->map(fn (string $conflict) => '&bull; ' . $conflict)
            ->implode('<br>');

        return 'There are already existing departments under this college with the same or similar code/name. '
            . 'This may cause a conflict or duplicate record.<br><br>'
            . $items
            . '<br><br>Do you want to continue creating this department anyway?';
    }
};
?>

<div class="">
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
            <x-button wire:click="editCollege" sm color="primary" icon="pencil" text="Edit Details" />
            <x-button tag="a" href="{{ route('admin.campuses.show', [$this->campus->id]) }}" sm outline
                text="Back to Colleges" />
        </div>
    </div>

    <div
        class="flex flex-col items-start justify-between gap-4 px-6 py-4 mb-6 bg-white rounded-lg shadow md:flex-row md:items-center dark:bg-gray-800">
        <h1 class="text-2xl font-bold dark:text-white">Department List</h1>
        <x-button wire:click="openCreateDepartmentModal" sm color="primary" icon="plus" text="New Department" />
    </div>

    <div class="bg-white p-6 rounded-lg shadow dark:bg-zinc-800">
        <livewire:admin.tables.departments-table :college-id="$college->id" />
    </div>

    <x-modal wire="collegeModal" title="Edit College Details" size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="College Code" wire:model="collegeForm.code" hint="Use a short code like CEIT or CAS." />
                <x-input label="College Name" wire:model="collegeForm.name" />
            </div>

            <x-input label="Campus" :value="$campus->code . ' - ' . $campus->name" disabled
                hint="This college belongs to the selected campus and cannot be changed here." />

            <x-textarea label="Description" wire:model="collegeForm.description"
                hint="Optional short description for this college." />

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="collegeForm.is_active" label="College is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $collegeForm->is_active ? 'This college is available for active assignments.' : 'This college will be marked inactive.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="closeCollegeModal" sm />
            <x-button color="primary" text="Save Changes" wire:click="confirmSaveCollege" sm />
        </x-slot:footer>
    </x-modal>

    <x-modal wire="departmentModal" title="{{ $isEditingDepartment ? 'Edit Department Details' : 'New Department' }}"
        size="3xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Department Code" wire:model="departmentForm.code"
                    hint="Use a short code like CEIT-ACAD." />
                <x-input label="Department Name" wire:model="departmentForm.name" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Campus" :value="$campus->code . ' - ' . $campus->name" disabled />
                <x-input label="College" :value="$college->code . ' - ' . $college->name" disabled />
            </div>

            <x-textarea label="Description" wire:model="departmentForm.description"
                hint="Optional short description for this department." />

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <x-toggle wire:model="departmentForm.is_active" label="Department is active" />
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $departmentForm->is_active ? 'This department is available for active assignments.' : 'This department will be marked inactive.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            <x-button flat text="Cancel" wire:click="closeDepartmentModal" sm />
            <x-button color="primary" :text="$isEditingDepartment ? 'Save Changes' : 'Create Department'" wire:click="confirmSaveDepartment" sm />
        </x-slot:footer>
    </x-modal>
</div>
