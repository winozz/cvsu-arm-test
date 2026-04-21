<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Role;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RolesForm extends Form
{
    public ?Role $role = null;

    public string $name = '';

    public string $guard_name = 'web';

    public array $permissions = [];

    public function setRole(Role $role): void
    {
        $this->role = $role;
        $this->name = $role->name;
        $this->guard_name = $role->guard_name;
        $this->permissions = $role->permissions()->pluck('permissions.id')->map(fn ($id) => (string) $id)->toArray();
    }

    public function resetForm(): void
    {
        $this->reset(['role', 'name', 'permissions']);
        $this->guard_name = 'web';
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    public function rules(): array
    {
        $roleId = $this->role?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->ignore($roleId)
                    ->where(fn ($query) => $query->where('guard_name', $this->guard_name)),
            ],
            'guard_name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }
}
