<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Form;

class UserForm extends Form
{
    public ?User $user = null;

    public string $first_name = '';

    public string $middle_name = '';

    public string $last_name = '';

    public string $email = '';

    public array $roles = [];

    public array $direct_permissions = [];

    public string $type = 'standard';

    public bool $is_active = true;

    public ?int $campus_id = null;

    public ?int $college_id = null;

    public ?int $department_id = null;

    public string $academic_rank = '';

    public string $contactno = '';

    public string $address = '';

    public string $sex = '';

    public string $birthday = '';

    public string $position = '';

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user?->id),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'direct_permissions' => ['nullable', 'array'],
            'direct_permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'type' => ['required', 'in:standard,faculty,employee,dual'],
            'is_active' => ['required', 'boolean'],
            'campus_id' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->requiresAssignment()),
                Rule::exists('campuses', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')
                ),
            ],
            'college_id' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->requiresAssignment()),
                Rule::exists('colleges', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->when(
                            filled($this->campus_id),
                            fn ($collegeQuery) => $collegeQuery->where('campus_id', $this->campus_id)
                        )
                ),
            ],
            'department_id' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->requiresFacultyProfile()),
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->when(
                            filled($this->campus_id),
                            fn ($departmentQuery) => $departmentQuery->where('campus_id', $this->campus_id)
                        )
                        ->when(
                            filled($this->college_id),
                            fn ($departmentQuery) => $departmentQuery->where('college_id', $this->college_id)
                        )
                ),
            ],
            'academic_rank' => ['nullable', 'string', 'max:255'],
            'contactno' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'sex' => ['nullable', 'in:Male,Female'],
            'birthday' => ['nullable', 'date'],
            'position' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $this->requiresEmployeeProfile()),
                'string',
                'max:255',
            ],
        ];
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    public function resetForm(): void
    {
        $this->reset();

        $this->user = null;
        $this->roles = [];
        $this->direct_permissions = [];
        $this->type = 'standard';
        $this->is_active = true;
    }

    public function setUser(User $user): void
    {
        $this->resetForm();

        $this->user = $user;
        $this->email = $user->email;
        $this->roles = $user->roles->pluck('name')->all();
        $this->direct_permissions = $user->getDirectPermissions()->pluck('name')->all();
        $this->is_active = (bool) $user->is_active;

        $facultyProfile = $user->facultyProfile;
        $employeeProfile = $user->employeeProfile;

        $this->type = match (true) {
            $facultyProfile !== null && $employeeProfile !== null => 'dual',
            $facultyProfile !== null => 'faculty',
            $employeeProfile !== null => 'employee',
            default => 'standard',
        };

        $sourceProfile = $facultyProfile ?? $employeeProfile;

        if ($sourceProfile) {
            $this->first_name = (string) ($sourceProfile->first_name ?? '');
            $this->middle_name = (string) ($sourceProfile->middle_name ?? '');
            $this->last_name = (string) ($sourceProfile->last_name ?? '');
            $this->campus_id = $sourceProfile->campus_id;
            $this->college_id = $sourceProfile->college_id;
            $this->department_id = $sourceProfile->department_id;
        } else {
            $parts = $this->splitDisplayName($user->name);

            $this->first_name = $parts['first_name'];
            $this->middle_name = $parts['middle_name'];
            $this->last_name = $parts['last_name'];
        }

        if ($facultyProfile) {
            $this->academic_rank = (string) ($facultyProfile->academic_rank ?? '');
            $this->contactno = (string) ($facultyProfile->contactno ?? '');
            $this->address = (string) ($facultyProfile->address ?? '');
            $this->sex = (string) ($facultyProfile->sex ?? '');
            $this->birthday = $facultyProfile->birthday?->format('Y-m-d') ?? '';
        }

        if ($employeeProfile) {
            $this->position = (string) ($employeeProfile->position ?? '');
        }
    }

    public function clearAssignment(): void
    {
        $this->campus_id = null;
        $this->college_id = null;
        $this->department_id = null;
    }

    public function requiresAssignment(): bool
    {
        return $this->type !== 'standard';
    }

    public function requiresFacultyProfile(): bool
    {
        return in_array($this->type, ['faculty', 'dual'], true);
    }

    public function requiresEmployeeProfile(): bool
    {
        return in_array($this->type, ['employee', 'dual'], true);
    }

    public function profileTypeLabel(): string
    {
        return match ($this->type) {
            'faculty' => 'Faculty',
            'employee' => 'Employee',
            'dual' => 'Faculty + Employee',
            default => 'Standard',
        };
    }

    public function fullName(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->map(fn (?string $value): string => trim((string) $value))
            ->filter()
            ->implode(' ');
    }

    public function resolveAcademicAssignment(): array
    {
        if ($this->department_id) {
            return [
                'campus_id' => (int) $this->campus_id,
                'college_id' => (int) $this->college_id,
                'department_id' => (int) $this->department_id,
            ];
        }

        return [
            'campus_id' => (int) $this->campus_id,
            'college_id' => (int) $this->college_id,
            'department_id' => null,
        ];
    }

    public function resolvedDirectPermissions(): Collection
    {
        return Permission::query()
            ->whereIn('name', $this->direct_permissions)
            ->get();
    }

    public function userAttributes(): array
    {
        return [
            'name' => $this->fullName(),
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
        ];
    }

    public function facultyProfileAttributes(array $assignment): array
    {
        return array_merge($assignment, $this->personNameAttributes(), [
            'email' => $this->email,
            'academic_rank' => $this->blankToNull($this->academic_rank),
            'contactno' => $this->blankToNull($this->contactno),
            'address' => $this->blankToNull($this->address),
            'sex' => $this->blankToNull($this->sex),
            'birthday' => $this->blankToNull($this->birthday),
        ]);
    }

    public function employeeProfileAttributes(array $assignment): array
    {
        return array_merge($assignment, $this->personNameAttributes(), [
            'position' => $this->blankToNull($this->position),
        ]);
    }

    protected function personNameAttributes(): array
    {
        return [
            'first_name' => $this->first_name,
            'middle_name' => $this->blankToNull($this->middle_name),
            'last_name' => $this->last_name,
        ];
    }

    protected function splitDisplayName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = array_shift($parts) ?? '';
        $lastName = count($parts) ? (string) array_pop($parts) : '';

        return [
            'first_name' => $firstName,
            'middle_name' => implode(' ', $parts),
            'last_name' => $lastName,
        ];
    }

    protected function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
