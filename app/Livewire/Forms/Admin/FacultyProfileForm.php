<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class FacultyProfileForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('nullable|string|max:255')]
    public string $middle_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    #[Validate('required|email|unique:users,email|unique:faculty_profiles,email')]
    public string $email = '';

    public ?int $campus_id = null;

    public ?int $college_id = null;

    public ?int $department_id = null;

    #[Validate('nullable|string|max:255')]
    public string $academic_rank = '';

    #[Validate('nullable|string|max:50')]
    public string $contactno = '';

    #[Validate('nullable|in:Male,Female')]
    public string $sex = '';

    #[Validate('nullable|date')]
    public string $birthday = '';

    #[Validate('nullable|string')]
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

    public function store(): void
    {
        $this->validate();

        $fullName = trim($this->first_name.' '.($this->middle_name ? $this->middle_name.' ' : '').$this->last_name);
        $assignment = $this->resolveAcademicAssignment();

        // Create the linked User account
        $user = User::create([
            'name' => $fullName,
            'email' => $this->email,
            'password' => Hash::make('password123'), // Default password
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Assign default faculty role
        $user->assignRole('faculty');

        // Create the Faculty Profile
        FacultyProfile::create(array_merge($assignment, [
            'user_id' => $user->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'academic_rank' => $this->academic_rank,
            'contactno' => $this->contactno,
            'sex' => $this->sex,
            'birthday' => $this->birthday ?: null,
            'address' => $this->address,
        ]));

        $this->reset();
    }

    /**
     * @return array{campus_id: int, college_id: int, department_id: int}
     */
    protected function resolveAcademicAssignment(): array
    {
        $department = Department::query()->findOrFail($this->department_id);

        return [
            'campus_id' => $department->campus_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ];
    }
}
