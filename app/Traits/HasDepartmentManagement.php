<?php

namespace App\Traits;

use App\Models\College;
use App\Models\Department;
use App\Support\DepartmentDuplicateDetector;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

/**
 * Shared department (and college edit) management for page components.
 *
 * Host class must declare:
 *   use CanManage, Interactions;
 *   public Campus $campus;
 *   public College $college;
 *   public CollegeForm $collegeForm;
 *   public DepartmentForm $departmentForm;
 *   public function mount(...): void { ... }
 */
trait HasDepartmentManagement
{
    // ─── State ───────────────────────────────────────────────────────────────

    public bool $collegeModal = false;

    public bool $departmentModal = false;

    public bool $isEditingDepartment = false;

    public bool $departmentDuplicateConflictDetected = false;

    public array $departmentDuplicateConflicts = [];

    public bool $departmentDuplicateConfirmed = false;

    // ─── College modal ────────────────────────────────────────────────────────

    public function editCollege(): void
    {
        $this->ensureCanManage('colleges.update');

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
        $this->ensureCanManage('colleges.update');

        $this->collegeForm->validateForm();
        $this->collegeModal = false;

        $this->dialog()
            ->question('Save Changes?', 'Are you sure you want to update this college?')
            ->confirm('Yes, save changes', 'saveCollege')
            ->cancel('Cancel', 'reopenCollegeModal')
            ->send();
    }

    public function saveCollege(): void
    {
        $this->ensureCanManage('colleges.update');

        try {
            $validated = $this->collegeForm->validateForm();
            $this->college->update($this->collegeForm->payload($validated));
            $this->college->refresh();
            $this->collegeModal = false;
            $this->toast()->success('Success', 'College details updated successfully.')->send();
        } catch (ValidationException $e) {
            $this->reopenCollegeModal();
            throw $e;
        } catch (Exception $e) {
            $this->reopenCollegeModal();
            Log::error('College Save Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the college.')->send();
        }
    }

    // ─── Department modal ─────────────────────────────────────────────────────

    #[On('openEditDepartmentModal')]
    public function openEditDepartmentModal(Department $department): void
    {
        $this->ensureCanManage('departments.update');

        abort_unless($department->college_id === $this->college->id, 404);

        $this->resetValidation();
        $this->departmentForm->setDepartment($department);
        $this->isEditingDepartment = true;
        $this->resetDepartmentDuplicateState();
        $this->departmentModal = true;
    }

    public function openCreateDepartmentModal(): void
    {
        $this->ensureCanManage('departments.create');

        $this->resetValidation();
        $this->departmentForm->resetForm($this->campus->id, $this->college->id);
        $this->isEditingDepartment = false;
        $this->resetDepartmentDuplicateState();
        $this->departmentModal = true;
    }

    public function closeDepartmentModal(): void
    {
        $this->departmentModal = false;
        $this->isEditingDepartment = false;
        $this->resetDepartmentDuplicateState();
        $this->resetValidation();
        $this->departmentForm->resetForm($this->campus->id, $this->college->id);
    }

    public function reopenDepartmentModal(): void
    {
        $this->departmentModal = true;
    }

    public function confirmSaveDepartment(): void
    {
        $this->ensureCanManage($this->isEditingDepartment ? 'departments.update' : 'departments.create');

        $this->departmentForm->validateForm();
        $this->departmentModal = false;

        if ($this->isEditingDepartment) {
            $this->resetDepartmentDuplicateState();
            $this->openDepartmentSaveDialog();

            return;
        }

        $conflicts = DepartmentDuplicateDetector::findPotentialConflicts(
            $this->college->id,
            $this->departmentForm->code,
            $this->departmentForm->name
        );

        if ($conflicts !== []) {
            $this->departmentDuplicateConflictDetected = true;
            $this->departmentDuplicateConflicts = $conflicts;

            $this->dialog()
                ->warning('Possible Duplicate Department', DepartmentDuplicateDetector::warningMessage($conflicts))
                ->confirm('Proceed anyway', 'proceedWithDuplicateDepartmentSave')
                ->cancel('Go Back', 'reopenDepartmentModal')
                ->send();

            return;
        }

        $this->resetDepartmentDuplicateState();
        $this->openDepartmentSaveDialog();
    }

    public function proceedWithDuplicateDepartmentSave(): void
    {
        $this->departmentDuplicateConfirmed = true;

        $this->saveDepartment();
    }

    public function saveDepartment(): void
    {
        $this->ensureCanManage($this->isEditingDepartment ? 'departments.update' : 'departments.create');

        try {
            if ($this->isEditingDepartment) {
                $validated = $this->departmentForm->validateForm();
                $this->departmentForm->department->update($this->departmentForm->payload($validated));
                $this->finalizeDepartmentSave('Department details updated successfully.');

                return;
            }

            if (! $this->departmentDuplicateConfirmed) {
                $conflicts = DepartmentDuplicateDetector::findPotentialConflicts(
                    $this->college->id,
                    $this->departmentForm->code,
                    $this->departmentForm->name
                );

                if ($conflicts !== []) {
                    $this->departmentModal = false;
                    $this->departmentDuplicateConflictDetected = true;
                    $this->departmentDuplicateConflicts = $conflicts;

                    $this->dialog()
                        ->warning('Possible Duplicate Department', DepartmentDuplicateDetector::warningMessage($conflicts))
                        ->confirm('Proceed anyway', 'proceedWithDuplicateDepartmentSave')
                        ->cancel('Go Back', 'reopenDepartmentModal')
                        ->send();

                    return;
                }
            }

            $validated = $this->departmentForm->validateForm();
            Department::create($this->departmentForm->payload($validated));
            $this->finalizeDepartmentSave('Department created successfully.');
        } catch (ValidationException $e) {
            $this->reopenDepartmentModal();
            throw $e;
        } catch (Exception $e) {
            $this->reopenDepartmentModal();
            Log::error('Department Save Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the department.')->send();
        }
    }

    // ─── Protected helpers ────────────────────────────────────────────────────

    protected function resetDepartmentDuplicateState(): void
    {
        $this->departmentDuplicateConflictDetected = false;
        $this->departmentDuplicateConflicts = [];
        $this->departmentDuplicateConfirmed = false;
    }

    protected function openDepartmentSaveDialog(): void
    {
        $title = $this->isEditingDepartment ? 'Save Changes?' : 'Create Department?';
        $description = $this->isEditingDepartment
            ? 'Are you sure you want to update this department?'
            : 'Are you sure you want to create this department?';
        $confirm = $this->isEditingDepartment ? 'Yes, save changes' : 'Yes, create department';

        $this->dialog()
            ->question($title, $description)
            ->confirm($confirm, 'saveDepartment')
            ->cancel('Cancel', 'reopenDepartmentModal')
            ->send();
    }

    protected function finalizeDepartmentSave(string $message): void
    {
        $this->departmentModal = false;
        $this->isEditingDepartment = false;
        $this->resetDepartmentDuplicateState();
        $this->dispatch('pg:eventRefresh-departmentsTable');
        $this->toast()->success('Success', $message)->send();
    }

    protected function syncDepartmentContextForms(): void
    {
        $this->collegeForm->setCollege($this->college);
        $this->departmentForm->resetForm($this->campus->id, $this->college->id);
    }

    protected function resolveFallbackCollegeContext(): void
    {
        $fallbackCollege = College::query()->with('campus')->orderBy('name')->first();

        abort_unless($fallbackCollege?->campus, 403);

        $this->campus = $fallbackCollege->campus;
        $this->college = $fallbackCollege;
    }
}
