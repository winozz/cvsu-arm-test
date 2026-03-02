<?php

namespace App\Livewire\Forms\Admin;

use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Validate;
use Livewire\Form;

class UsersForm extends Form
{
    #[Validate('required|string|max:255')]
    public string $first_name = '';

    #[Validate('required|string|max:255')]
    public string $last_name = '';

    #[Validate('required|email|unique:users,email')]
    public string $email = '';

    #[Validate('required|array')]
    public array $roles = [];

    #[Validate('required|in:faculty,employee')]
    public string $type = '';

    // Removed strict type hint to allow HTML select string values
    #[Validate('required|exists:branches,id')]
    public $branch_id = null;

    // Removed strict type hint to allow HTML select string values
    #[Validate('required|exists:departments,id')]
    public $department_id = null;

    public function rules()
    {
        return [
            'roles.*' => 'exists:roles,name',
        ];
    }

    public function store()
    {
        $this->validate();

        $fullName = trim($this->first_name.' '.$this->last_name);

        $user = User::create([
            'name' => $fullName,
            'email' => $this->email,
            'password' => Hash::make('password123'),
        ]);

        $user->assignRole($this->roles);

        if ($this->type === 'faculty') {
            FacultyProfile::create([
                'user_id' => $user->id,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'branch_id' => $this->branch_id,
                'department_id' => $this->department_id,
            ]);
        } else {
            EmployeeProfile::create([
                'user_id' => $user->id,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'branch_id' => $this->branch_id,
                'department_id' => $this->department_id,
            ]);
        }

        $this->reset();
    }
}
