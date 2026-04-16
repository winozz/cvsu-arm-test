<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

#[Fillable(['name', 'guard_name'])]
class Permission extends SpatiePermission
{
    use SoftDeletes;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            config('permission.table_names.role_has_permissions'),
            app(\Spatie\Permission\PermissionRegistrar::class)->pivotPermission,
            app(\Spatie\Permission\PermissionRegistrar::class)->pivotRole
        );
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes) => Str::headline(str_replace(['.', '_'], ' ', $attributes['name'] ?? '')),
        );
    }
}
