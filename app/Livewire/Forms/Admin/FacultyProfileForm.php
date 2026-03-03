<?php

namespace App\Livewire\Forms\Admin;

use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

    #[Validate('required|exists:branches,id')]
    public $branch_id = null;

    #[Validate('required|exists:departments,id')]
    public $department_id = null;

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

    public function store()
    {
        $this->validate();

        $fullName = trim($this->first_name . ' ' . ($this->middle_name ? $this->middle_name . ' ' : '') . $this->last_name);

        // Create the linked User account
        $user = User::create([
            'name' => $fullName,
            'email' => $this->email,
            'password' => Hash::make('password123'), // Default password
        ]);

        // Assign default faculty role
        $user->assignRole('faculty');

        // Create the Faculty Profile
        FacultyProfile::create([
            'user_id' => $user->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'branch_id' => $this->branch_id,
            'department_id' => $this->department_id,
            'academic_rank' => $this->academic_rank,
            'contactno' => $this->contactno,
            'sex' => $this->sex,
            'birthday' => $this->birthday,
            'address' => $this->address,
        ]);

        $this->reset();
    }
}
