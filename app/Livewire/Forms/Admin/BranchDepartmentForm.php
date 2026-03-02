<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Department;
use Livewire\Attributes\Validate;
use Livewire\Form;

class BranchDepartmentForm extends Form
{
    public ?Department $department = null;

    #[Validate('required|exists:branches,id')]
    public ?int $branch_id = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:50')]
    public string $code = '';

    #[Validate('boolean')]
    public bool $is_active = true;

    public function setDepartment(Department $department)
    {
        $this->department = $department;
        $this->branch_id = $department->branch_id;
        $this->name = $department->name;
        $this->code = $department->code;
        $this->is_active = $department->is_active;
    }

    public function store()
    {
        $this->validate();
        Department::create($this->except('department'));
        $this->reset(['name', 'code', 'is_active']);
    }

    public function update()
    {
        $this->validate();
        $this->department->update($this->except('department'));
        $this->reset(['name', 'code', 'is_active', 'department']);
    }
}
