<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;

class ReferenceDataService
{
    /**
     * Get all active campuses as select options, cached for 1 hour.
     */
    public function campuses(): array
    {
        return cache()->remember('reference-data:campuses', 3600, fn () => Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($campus) => ['label' => $campus->name, 'value' => $campus->id])
            ->toArray()
        );
    }

    /**
     * Get all non-deleted roles as select options, cached for 1 hour.
     */
    public function roles(): array
    {
        return cache()->remember('reference-data:roles', 3600, fn () => Role::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn ($role) => ['label' => $role->display_name, 'value' => $role->name])
            ->toArray()
        );
    }

    /**
     * Get all non-deleted permissions as select options, cached for 1 hour.
     */
    public function permissions(): array
    {
        return cache()->remember('reference-data:permissions', 3600, fn () => Permission::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn ($permission) => ['label' => $permission->display_name, 'value' => $permission->name])
            ->toArray()
        );
    }

    /**
     * Get colleges for a specific campus, cached per campus for 1 hour.
     */
    public function collegesForCampus(int $campusId): array
    {
        return cache()->remember("reference-data:colleges::{$campusId}", 3600, fn () => College::query()
            ->where('campus_id', $campusId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($college) => ['label' => $college->name, 'value' => $college->id])
            ->toArray()
        );
    }

    /**
     * Get departments for a specific college, cached per college for 1 hour.
     */
    public function departmentsForCollege(int $collegeId): array
    {
        return cache()->remember("reference-data:departments::{$collegeId}", 3600, fn () => Department::query()
            ->where('college_id', $collegeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($department) => ['label' => $department->name, 'value' => $department->id])
            ->toArray()
        );
    }

    /**
     * Get all roles as select options with role names as labels, cached for 1 hour.
     * Used in assignments page where role name is displayed as label.
     */
    public function rolesAsNames(): array
    {
        return cache()->remember('reference-data:roles-as-names', 3600, fn () => Role::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn ($role) => ['label' => $role->name, 'value' => $role->name])
            ->toArray()
        );
    }

    /**
     * Get all permissions as select options with permission names as labels, cached for 1 hour.
     * Used in assignments page where permission name is displayed as label.
     */
    public function permissionsAsNames(): array
    {
        return cache()->remember('reference-data:permissions-as-names', 3600, fn () => Permission::query()
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['name'])
            ->map(fn ($permission) => ['label' => $permission->name, 'value' => $permission->name])
            ->toArray()
        );
    }

    /**
     * Clear all reference data cache entries.
     * Useful after creating/updating reference records.
     */
    public function clearCache(): void
    {
        cache()->forget('reference-data:campuses');
        cache()->forget('reference-data:roles');
        cache()->forget('reference-data:permissions');
        cache()->forget('reference-data:roles-as-names');
        cache()->forget('reference-data:permissions-as-names');
        // Note: Filtered caches (colleges, departments) should be cleared individually by caller
    }

    /**
     * Clear campus-specific caches.
     */
    public function clearCampusCache(int $campusId): void
    {
        cache()->forget("reference-data:colleges::{$campusId}");
    }

    /**
     * Clear college-specific caches.
     */
    public function clearCollegeCache(int $collegeId): void
    {
        cache()->forget("reference-data:departments::{$collegeId}");
    }
}
