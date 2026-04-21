<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Department;
use Illuminate\Validation\Rule;
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
            'campus_id' => 'required|exists:campuses,id',
            'college_id' => [
                'required',
                Rule::exists('colleges', 'id')->where(
                    fn ($query) => $query->where('campus_id', $this->campus_id)
                ),
            ],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query->where('college_id', $this->college_id)
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
        return $this->validate($this->rules());
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
