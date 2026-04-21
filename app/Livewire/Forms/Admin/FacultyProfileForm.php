<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class FacultyProfileForm extends Form
{
    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $email = '';

    public ?int $campus_id = null;

    public ?int $college_id = null;

    public ?int $department_id = null;

    public string $academic_rank = '';

    public string $contactno = '';

    public string $sex = '';

    public string $birthday = '';

    public string $address = '';

    public ?int $managed_campus_id = null;

    public ?int $managed_college_id = null;

    public ?int $managed_department_id = null;

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
                Rule::unique('faculty_profiles', 'email')->whereNull('deleted_at'),
            ],
            'campus_id' => ['required', Rule::in(array_filter([$this->managed_campus_id]))],
            'college_id' => ['required', Rule::in(array_filter([$this->managed_college_id]))],
            'department_id' => [
                'required',
                filled($this->managed_department_id)
                    ? Rule::in([$this->managed_department_id])
                    : Rule::exists('departments', 'id')->where(
                        fn ($query) => $query->where('college_id', $this->managed_college_id)
                    ),
            ],
            'academic_rank' => 'nullable|string|max:255',
            'contactno' => 'nullable|string|max:50',
            'sex' => 'nullable|in:Male,Female',
            'birthday' => 'nullable|date',
            'address' => 'nullable|string',
        ];
    }

    public function validateForm(): array
    {
        $validated = $this->validate($this->rules());

        $assignment = $this->resolveAcademicAssignment();

        if ($assignment['campus_id'] !== $this->managed_campus_id || $assignment['college_id'] !== $this->managed_college_id) {
            throw ValidationException::withMessages([
                'form.department_id' => 'The selected department is outside your allowed assignment scope.',
            ]);
        }

        return $validated;
    }

    public function constrainToManager(EmployeeProfile|FacultyProfile $profile): void
    {
        $this->managed_campus_id = $profile->campus_id;
        $this->managed_college_id = $profile->college_id;
        $this->managed_department_id = $profile->department_id;
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.($this->middle_name ? $this->middle_name.' ' : '').$this->last_name);
    }

    public function resetForm(): void
    {
        $this->reset();
    }

    public function resolveAcademicAssignment(): array
    {
        $department = Department::query()->findOrFail($this->department_id);

        return [
            'campus_id' => $department->campus_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ];
    }
}
