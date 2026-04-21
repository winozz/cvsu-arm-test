<?php

namespace App\Traits;

use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasManagedFacultyProfiles
{
    protected function managedAcademicProfile(): EmployeeProfile|FacultyProfile
    {
        $profile = Auth::user()?->departmentManagementProfile();

        abort_unless($profile && filled($profile->college_id), 403);

        return $profile;
    }

    protected function managedFacultyProfileQuery(bool $includeTrashed = false): Builder
    {
        $profile = $this->managedAcademicProfile();

        return FacultyProfile::query()
            ->when(
                filled($profile->department_id),
                fn (Builder $query) => $query->where('department_id', $profile->department_id),
                fn (Builder $query) => $query->where('college_id', $profile->college_id)
            )
            ->when($includeTrashed, fn (Builder $query) => $query->withTrashed());
    }

    protected function findManagedFacultyProfile(int $id, bool $includeTrashed = false): FacultyProfile
    {
        return $this->managedFacultyProfileQuery($includeTrashed)
            ->where('id', $id)
            ->firstOrFail();
    }
}
