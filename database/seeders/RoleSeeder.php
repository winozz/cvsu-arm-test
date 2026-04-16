<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = array_map(
            fn (PermissionEnum $permission): string => $permission->value,
            PermissionEnum::cases()
        );

        $rolePermissions = [
            'superAdmin' => $allPermissions,

            'collegeAdmin' => [
                PermissionEnum::DASHBOARD_VIEW->value,
                PermissionEnum::PROFILE_VIEW->value,
                PermissionEnum::PROFILE_UPDATE->value,

                PermissionEnum::DEPARTMENT_CREATE->value,
                PermissionEnum::DEPARTMENT_VIEW->value,
                PermissionEnum::DEPARTMENT_UPDATE->value,
                PermissionEnum::DEPARTMENT_DELETE->value,
                PermissionEnum::DEPARTMENT_RESTORE->value,

                PermissionEnum::SCHEDULE_VIEW->value,
                PermissionEnum::FACULTY_PROFILE_VIEW->value,
                PermissionEnum::FACULTY_PROFILE_UPDATE->value,
                PermissionEnum::COLLEGE_VIEW->value,
                PermissionEnum::COLLEGE_UPDATE->value,
                PermissionEnum::PROGRAM_VIEW->value,
                PermissionEnum::SUBJECT_VIEW->value,
                PermissionEnum::ROOM_VIEW->value,
            ],

            'deptAdmin' => [
                PermissionEnum::DASHBOARD_VIEW->value,
                PermissionEnum::PROFILE_VIEW->value,
                PermissionEnum::PROFILE_UPDATE->value,

                PermissionEnum::SCHEDULE_CREATE->value,
                PermissionEnum::SCHEDULE_VIEW->value,
                PermissionEnum::SCHEDULE_UPDATE->value,
                PermissionEnum::SCHEDULE_DELETE->value,
                PermissionEnum::SCHEDULE_RESTORE->value,
                PermissionEnum::SCHEDULE_ASSIGN->value,

                PermissionEnum::FACULTY_PROFILE_CREATE->value,
                PermissionEnum::FACULTY_PROFILE_VIEW->value,
                PermissionEnum::FACULTY_PROFILE_UPDATE->value,
                PermissionEnum::FACULTY_PROFILE_DELETE->value,
                PermissionEnum::FACULTY_PROFILE_RESTORE->value,

                PermissionEnum::PROGRAM_CREATE->value,
                PermissionEnum::PROGRAM_VIEW->value,
                PermissionEnum::PROGRAM_UPDATE->value,
                PermissionEnum::PROGRAM_DELETE->value,
                PermissionEnum::PROGRAM_RESTORE->value,

                PermissionEnum::SUBJECT_CREATE->value,
                PermissionEnum::SUBJECT_VIEW->value,
                PermissionEnum::SUBJECT_UPDATE->value,
                PermissionEnum::SUBJECT_DELETE->value,
                PermissionEnum::SUBJECT_RESTORE->value,

                PermissionEnum::ROOM_CREATE->value,
                PermissionEnum::ROOM_VIEW->value,
                PermissionEnum::ROOM_UPDATE->value,
                PermissionEnum::ROOM_DELETE->value,
                PermissionEnum::ROOM_RESTORE->value,
            ],

            'faculty' => [
                PermissionEnum::DASHBOARD_VIEW->value,
                PermissionEnum::PROFILE_VIEW->value,
                PermissionEnum::PROFILE_UPDATE->value,
                PermissionEnum::FACULTY_SCHEDULE_VIEW->value,
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::withTrashed()->firstOrNew([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->guard_name = 'web';
            $role->save();

            if ($role->trashed()) {
                $role->restore();
            }

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
