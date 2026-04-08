<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the application's default permissions.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $expectedPermissions = collect(PermissionEnum::cases())
            ->map(fn (PermissionEnum $permissionEnum): string => $permissionEnum->value)
            ->all();

        foreach (PermissionEnum::cases() as $permissionEnum) {
            $permission = Permission::withTrashed()->firstOrNew([
                'name' => $permissionEnum->value,
                'guard_name' => 'web',
            ]);

            $permission->guard_name = 'web';
            $permission->save();

            if ($permission->trashed()) {
                $permission->restore();
            }
        }

        Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $expectedPermissions)
            ->get()
            ->each(function (Permission $permission): void {
                if (! $permission->trashed()) {
                    $permission->delete();
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
