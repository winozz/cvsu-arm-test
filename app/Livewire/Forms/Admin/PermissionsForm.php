<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Permission;
use App\Traits\CanManage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class PermissionsForm extends Form
{
    use CanManage;

    public ?Permission $permission = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:255')]
    public string $guard_name = 'web';

    public function setPermission(Permission $permission): void
    {
        $this->permission = $permission;
        $this->name = $permission->name;
        $this->guard_name = $permission->guard_name;
    }

    public function store(): Permission
    {
        $this->ensureCanManage('permissions.create');

        $validated = $this->validate($this->rules());

        $permission = Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        $this->resetForm();

        return $permission;
    }

    public function update(): void
    {
        $this->ensureCanManage('permissions.update');

        $validated = $this->validate($this->rules());

        $this->permission?->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        $this->resetForm();
    }

    protected function rules(): array
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

    public function resetForm(): void
    {
        $this->reset(['permission', 'name']);
        $this->guard_name = 'web';
    }
}
