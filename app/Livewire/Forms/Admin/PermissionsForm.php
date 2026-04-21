<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Permission;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PermissionsForm extends Form
{
    public ?Permission $permission = null;

    public string $name = '';

    public string $guard_name = 'web';

    public function setPermission(Permission $permission): void
    {
        $this->permission = $permission;
        $this->name = $permission->name;
        $this->guard_name = $permission->guard_name;
    }

    public function resetForm(): void
    {
        $this->reset(['permission', 'name']);
        $this->guard_name = 'web';
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    public function rules(): array
    {
        $permissionId = $this->permission?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->ignore($permissionId)
                    ->where(fn ($query) => $query->where('guard_name', $this->guard_name)),
            ],
            'guard_name' => ['required', 'string', 'max:255'],
        ];
    }
}
