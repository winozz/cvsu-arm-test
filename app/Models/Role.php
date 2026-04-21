<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

#[Fillable(['name', 'guard_name'])]
class Role extends SpatieRole
{
    use SoftDeletes;

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            config('permission.table_names.role_has_permissions'),
            app(\Spatie\Permission\PermissionRegistrar::class)->pivotRole,
            app(\Spatie\Permission\PermissionRegistrar::class)->pivotPermission
        );
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes) => Str::headline($attributes['name'] ?? ''),
        );
    }
}
