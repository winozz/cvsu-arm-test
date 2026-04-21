<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Department;
use App\Models\FacultyProfile;
use Illuminate\Validation\Rule;
use Livewire\Form;

class FacultyProfileUpdateForm extends Form
{
    public ?FacultyProfile $profile = null;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $email = '';

    public ?int $campus_id = null;

    public ?int $college_id = null;

    public ?int $department_id = null;

    public string $academic_rank = '';

    public string $contactno = '';

    public string $address = '';

    public string $sex = '';

    public string $birthday = '';

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('faculty_profiles', 'email')
                    ->ignore($this->profile?->id)
                    ->whereNull('deleted_at'),
                Rule::unique('users', 'email')
                    ->ignore($this->profile?->user_id)
                    ->whereNull('deleted_at'),
            ],
            'campus_id' => ['required', Rule::in(array_filter([$this->profile?->campus_id]))],
            'college_id' => ['required', Rule::in(array_filter([$this->profile?->college_id]))],
            'department_id' => ['required', Rule::in(array_filter([$this->profile?->department_id]))],
            'academic_rank' => ['nullable', 'string', 'max:255'],
            'contactno' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'sex' => ['nullable', 'in:Male,Female'],
            'birthday' => ['nullable', 'date'],
        ];
    }

    public function setValues(FacultyProfile $profile): void
    {
        $this->profile = $profile;
        $this->first_name = $profile->first_name;
        $this->middle_name = $profile->middle_name ?? '';
        $this->last_name = $profile->last_name;
        $this->email = $profile->email;
        $this->campus_id = $profile->campus_id;
        $this->college_id = $profile->college_id;
        $this->department_id = $profile->department_id;
        $this->academic_rank = $profile->academic_rank ?? '';
        $this->contactno = $profile->contactno ?? '';
        $this->address = $profile->address ?? '';
        $this->sex = $profile->sex ?? '';
        $this->birthday = $profile->birthday ?? '';
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    public function fullName(): string
    {
        return trim(
            $this->first_name
            .($this->middle_name ? ' '.$this->middle_name : '')
            .' '.$this->last_name
        );
    }

    public function resolveAcademicAssignment(): array
    {
        if ($this->profile) {
            return [
                'campus_id' => $this->profile->campus_id,
                'college_id' => $this->profile->college_id,
                'department_id' => $this->profile->department_id,
            ];
        }

        $department = Department::query()->findOrFail($this->department_id);

        return [
            'campus_id' => $department->campus_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ];
    }
}
