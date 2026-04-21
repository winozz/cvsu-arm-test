<?php

namespace App\Support\UserManagement;

use App\Livewire\Forms\Admin\UserForm;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAccountWriter
{
    public function save(UserForm $form, ?User $user = null): User
    {
        return DB::transaction(function () use ($form, $user): User {
            $account = $user ?? new User;

            $this->saveAccount($account, $form);
            $this->syncRoles($account, $form->roles);
            $this->syncDirectPermissions($account, $form->resolvedDirectPermissions());

            $assignment = $form->requiresAssignment()
                ? $form->resolveAcademicAssignment()
                : [];

            $this->syncProfiles($account, $form, $assignment);

            return $account->load($this->relations());
        });
    }

    protected function saveAccount(User $account, UserForm $form): void
    {
        $account->fill($form->userAttributes());

        if (! $account->exists) {
            $account->password = $account->password ?: Hash::make('password123');
            $account->email_verified_at = $account->email_verified_at ?? now();
        }

        if (! $account->exists || $account->isDirty()) {
            $account->save();
        }
    }

    protected function syncProfiles(User $user, UserForm $form, array $assignment): void
    {
        if ($form->requiresFacultyProfile()) {
            $this->saveFacultyProfile($user, $form, $assignment);
        } else {
            $this->deleteProfile($this->resolveFacultyProfile($user), $user, 'facultyProfile');
        }

        if ($form->requiresEmployeeProfile()) {
            $this->saveEmployeeProfile($user, $form, $assignment);
        } else {
            $this->deleteProfile($this->resolveEmployeeProfile($user), $user, 'employeeProfile');
        }
    }

    protected function saveFacultyProfile(User $user, UserForm $form, array $assignment): void
    {
        $profile = $this->resolveFacultyProfile($user) ?? new FacultyProfile(['user_id' => $user->id]);

        if ($profile->trashed()) {
            $profile->restore();
        }

        $profile->fill($form->facultyProfileAttributes($assignment));
        $profile->user_id = $user->id;

        if (! $profile->exists || $profile->isDirty()) {
            $profile->save();
        }

        $user->setRelation('facultyProfile', $profile);
    }

    protected function saveEmployeeProfile(User $user, UserForm $form, array $assignment): void
    {
        $profile = $this->resolveEmployeeProfile($user) ?? new EmployeeProfile(['user_id' => $user->id]);

        if ($profile->trashed()) {
            $profile->restore();
        }

        $profile->fill($form->employeeProfileAttributes($assignment));
        $profile->user_id = $user->id;

        if (! $profile->exists || $profile->isDirty()) {
            $profile->save();
        }

        $user->setRelation('employeeProfile', $profile);
    }

    protected function syncRoles(User $user, array $roles): void
    {
        if (! $this->sameValues($this->currentRoleNames($user), $roles)) {
            $user->syncRoles($roles);
        }
    }

    protected function syncDirectPermissions(User $user, Collection $permissions): void
    {
        $permissionNames = $permissions->pluck('name')->all();

        if (! $this->sameValues($this->currentDirectPermissionNames($user), $permissionNames)) {
            $user->syncPermissions($permissions);
        }
    }

    protected function currentRoleNames(User $user): array
    {
        return $user->relationLoaded('roles')
            ? $user->roles->pluck('name')->all()
            : $user->roles()->pluck('name')->all();
    }

    protected function currentDirectPermissionNames(User $user): array
    {
        return method_exists($user, 'relationLoaded') && $user->relationLoaded('permissions')
            ? $user->permissions->pluck('name')->all()
            : $user->permissions()->pluck('name')->all();
    }

    protected function sameValues(array $current, array $incoming): bool
    {
        sort($current);
        sort($incoming);

        return $current === $incoming;
    }

    protected function resolveFacultyProfile(User $user): ?FacultyProfile
    {
        if ($user->relationLoaded('facultyProfile') && $user->facultyProfile) {
            return $user->facultyProfile;
        }

        return FacultyProfile::withTrashed()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    protected function resolveEmployeeProfile(User $user): ?EmployeeProfile
    {
        if ($user->relationLoaded('employeeProfile') && $user->employeeProfile) {
            return $user->employeeProfile;
        }

        return EmployeeProfile::withTrashed()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();
    }

    protected function deleteProfile(?Model $profile, User $user, string $relation): void
    {
        if ($profile?->exists) {
            $profile->delete();
        }

        $user->unsetRelation($relation);
    }

    protected function relations(): array
    {
        return [
            'roles',
            'facultyProfile.campus',
            'facultyProfile.college',
            'facultyProfile.department',
            'employeeProfile.campus',
            'employeeProfile.college',
            'employeeProfile.department',
        ];
    }
}
