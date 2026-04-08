<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate;
use Livewire\Form;

class RolesForm extends Form
{
    use CanManage;

    public ?Role $role = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:255')]
    public string $guard_name = 'web';

    public array $permissions = [];

    public function setRole(Role $role): void
    {
        $this->role = $role;
        $this->name = $role->name;
        $this->guard_name = $role->guard_name;
        $this->permissions = $role->permissions()->pluck('permissions.id')->map(fn ($id) => (string) $id)->toArray();
    }

    public function store(): Role
    {
        $validated = $this->validate($this->rules());

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        $role->syncPermissions($this->resolvePermissions($validated['guard_name']));

        $this->resetForm();

        return $role;
    }

    public function update(): void
    {
        $validated = $this->validate($this->rules());

        $this->role?->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'],
        ]);

        $this->role?->syncPermissions($this->resolvePermissions($validated['guard_name']));

        $this->resetForm();
    }

    protected function rules(): array
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

    public function resetForm(): void
    {
        $this->reset(['role', 'name', 'permissions']);
        $this->guard_name = 'web';
    }

    protected function resolvePermissions(string $guardName): EloquentCollection
    {
        return Permission::query()
            ->where('guard_name', $guardName)
            ->whereIn('id', array_map('intval', $this->permissions))
            ->get();
    }
}
