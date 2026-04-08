<?php

namespace App\Livewire\Forms\Admin;

use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Form;

class UsersForm extends Form
{
    public ?User $user = null;

    // 1. Added ? to make properties nullable
    public ?string $first_name = '';

    public ?string $middle_name = '';

    public ?string $last_name = '';

    public ?string $email = '';

    public array $roles = [];

    public ?string $type = 'standard';

    public ?int $campus_id = null;

    public ?int $college_id = null;

    public ?int $department_id = null;

    // Faculty Specific Fields
    public ?string $academic_rank = '';

    public ?string $contactno = '';

    public ?string $address = '';

    public ?string $sex = '';

    public ?string $birthday = '';

    // Employee Specific Fields
    public ?string $position = '';

    public function rules()
    {
        return [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($this->user?->id)
                    ->whereNull('deleted_at'),
            ],
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,name',
            'type' => 'required|in:faculty,employee,standard',

            'campus_id' => 'exclude_if:type,standard|required|exists:campuses,id',
            'college_id' => [
                'exclude_if:type,standard',
                'required',
                Rule::exists('colleges', 'id')->where(
                    fn ($query) => $query->where('campus_id', $this->campus_id)
                ),
            ],
            'department_id' => [
                'exclude_if:type,standard',
                'nullable',
                Rule::requiredIf(fn () => $this->type === 'faculty'),
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query->where('college_id', $this->college_id)
                ),
            ],

            'position' => Rule::requiredIf(fn () => $this->type === 'employee'),
            'sex' => 'nullable|in:Male,Female',
            'birthday' => 'nullable|date',
        ];
    }

    public function setValues(User $user)
    {
        $this->user = $user;
        $this->email = $user->email ?? '';
        $this->roles = $user->roles->pluck('name')->toArray();

        $faculty = $user->facultyProfile;
        $employee = $user->employeeProfile;

        if ($faculty) {
            $this->type = 'faculty';
            $profile = $faculty;
        } elseif ($employee) {
            $this->type = 'employee';
            $profile = $employee;
        } else {
            $this->type = 'standard';
            $profile = null;
        }

        if ($profile) {
            // 2. Added ?? '' fallback for safe hydration
            $this->first_name = $profile->first_name ?? '';
            $this->middle_name = $profile->middle_name ?? '';
            $this->last_name = $profile->last_name ?? '';
            $this->campus_id = $profile->campus_id;
            $this->college_id = $profile->college_id;
            $this->department_id = $profile->department_id;

            if ($this->type === 'faculty') {
                $this->academic_rank = $profile->academic_rank ?? '';
                $this->contactno = $profile->contactno ?? '';
                $this->address = $profile->address ?? '';
                $this->sex = $profile->sex ?? '';
                $this->birthday = $profile->birthday ?? '';
            } else {
                $this->position = $profile->position ?? '';
            }
        } else {
            $nameParts = explode(' ', $user->name, 2);
            $this->first_name = $nameParts[0] ?? '';
            $this->last_name = $nameParts[1] ?? '';
            $this->middle_name = ''; // Explicitly set to string to clear any old state
            $this->campus_id = null;
            $this->college_id = null;
            $this->department_id = null;
        }
    }

    public function store(): void
    {
        $this->validate();

        DB::transaction(function (): void {
            $fullName = trim($this->first_name.' '.($this->middle_name ? $this->middle_name.' ' : '').$this->last_name);
            $assignment = $this->resolveAcademicAssignment();

            $newUser = User::create([
                'name' => $fullName,
                'email' => $this->email,
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $newUser->syncRoles($this->roles);

            if ($this->type === 'faculty') {
                FacultyProfile::create(array_merge($assignment, [
                    'user_id' => $newUser->id,
                    'first_name' => $this->first_name,
                    'middle_name' => $this->middle_name,
                    'last_name' => $this->last_name,
                    'email' => $this->email,
                    'academic_rank' => $this->academic_rank,
                    'contactno' => $this->contactno,
                    'address' => $this->address,
                    'sex' => $this->sex,
                    'birthday' => $this->birthday ?: null,
                ]));
            } elseif ($this->type === 'employee') {
                EmployeeProfile::create(array_merge($assignment, [
                    'user_id' => $newUser->id,
                    'first_name' => $this->first_name,
                    'middle_name' => $this->middle_name,
                    'last_name' => $this->last_name,
                    'position' => $this->position,
                ]));
            }
        });
    }

    public function update(): void
    {
        $this->validate();

        DB::transaction(function (): void {
            $fullName = trim($this->first_name.' '.($this->middle_name ? $this->middle_name.' ' : '').$this->last_name);
            $assignment = $this->type === 'standard'
                ? ['campus_id' => null, 'college_id' => null, 'department_id' => null]
                : $this->resolveAcademicAssignment();

            $this->user->update([
                'name' => $fullName,
                'email' => $this->email,
            ]);

            $this->user->syncRoles($this->roles);

            if ($this->type === 'standard') {
                FacultyProfile::where('user_id', $this->user->id)->delete();
                EmployeeProfile::where('user_id', $this->user->id)->delete();
            } else {
                $profileData = [
                    'first_name' => $this->first_name,
                    'middle_name' => $this->middle_name,
                    'last_name' => $this->last_name,
                    'email' => $this->email,
                ];

                if ($this->type === 'faculty') {
                    EmployeeProfile::where('user_id', $this->user->id)->delete();

                    FacultyProfile::updateOrCreate(
                        ['user_id' => $this->user->id],
                        array_merge($profileData, $assignment, [
                            'academic_rank' => $this->academic_rank,
                            'contactno' => $this->contactno,
                            'address' => $this->address,
                            'sex' => $this->sex,
                            'birthday' => $this->birthday ?: null,
                        ])
                    );
                } elseif ($this->type === 'employee') {
                    FacultyProfile::where('user_id', $this->user->id)->delete();

                    EmployeeProfile::updateOrCreate(
                        ['user_id' => $this->user->id],
                        array_merge($assignment, [
                            'first_name' => $this->first_name,
                            'middle_name' => $this->middle_name,
                            'last_name' => $this->last_name,
                            'position' => $this->position,
                        ])
                    );
                }
            }
        });
    }

    /**
     * @return array{campus_id: int, college_id: int, department_id: ?int}
     */
    protected function resolveAcademicAssignment(): array
    {
        if ($this->department_id) {
            $department = Department::query()->findOrFail($this->department_id);

            return [
                'campus_id' => $department->campus_id,
                'college_id' => $department->college_id,
                'department_id' => $department->id,
            ];
        }

        $college = College::query()->findOrFail($this->college_id);

        return [
            'campus_id' => $college->campus_id,
            'college_id' => $college->id,
            'department_id' => null,
        ];
    }
}
