<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Department;
use Livewire\Form;
use LogicException;

class DepartmentForm extends Form
{
    public ?Department $department = null;

    public ?int $campus_id = null;

    public ?int $college_id = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public function setContext(int $campusId, int $collegeId): void
    {
        $this->campus_id = $campusId;
        $this->college_id = $collegeId;
    }

    public function setDepartment(Department $department): void
    {
        $this->department = $department;
        $this->campus_id = $department->campus_id;
        $this->college_id = $department->college_id;
        $this->code = $department->code;
        $this->name = $department->name;
        $this->description = $department->description ?? '';
        $this->is_active = $department->is_active;
    }

    public function store(): Department
    {
        if (! $this->campus_id || ! $this->college_id) {
            throw new LogicException('Cannot create a department without campus and college context.');
        }

        $validated = $this->validateForm();

        $department = Department::create($this->payload($validated));

        $this->resetForm($this->campus_id, $this->college_id);

        return $department;
    }

    public function update(): Department
    {
        if (! $this->department) {
            throw new LogicException('Cannot update a department without an active record.');
        }

        $validated = $this->validateForm();

        $this->department->update($this->payload($validated));

        $department = $this->department->fresh();

        $this->resetForm($validated['campus_id'], $validated['college_id']);

        return $department;
    }

    public function resetForm(?int $campusId = null, ?int $collegeId = null): void
    {
        $this->reset(['department', 'code', 'name', 'description']);
        $this->campus_id = $campusId;
        $this->college_id = $collegeId;
        $this->is_active = true;
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    protected function rules(): array
    {
        return [
            'campus_id' => ['required', 'exists:campuses,id'],
            'college_id' => ['required', 'exists:colleges,id'],
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function payload(array $validated): array
    {
        return [
            'campus_id' => (int) $validated['campus_id'],
            'college_id' => (int) $validated['college_id'],
            'code' => trim($validated['code']),
            'name' => trim($validated['name']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
