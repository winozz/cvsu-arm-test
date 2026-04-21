<?php

namespace App\Traits;

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;

trait ResolvesUserFormOptions
{
    #[Computed(persist: true, seconds: 600)]
    public function campuses(): array
    {
        return Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Campus $campus): array => [
                'label' => $campus->name,
                'value' => $campus->id,
            ])
            ->all();
    }

    #[Computed(persist: true, seconds: 600)]
    public function availableRoles(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'label' => Str::headline($role->name),
                'value' => $role->name,
            ])
            ->all();
    }

    #[Computed(persist: true, seconds: 600)]
    public function availablePermissions(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission): array => [
                'label' => Str::headline(str_replace(['.', '_'], ' ', $permission->name)),
                'value' => $permission->name,
            ])
            ->all();
    }
}
