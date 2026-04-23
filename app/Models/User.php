<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'google_id',
        'avatar',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Relationships declaration
     */
    /**
     * Get the profile associated with the Profile
     */
    public function facultyProfile(): HasOne
    {
        return $this->hasOne(FacultyProfile::class, 'user_id', 'id');
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class, 'user_id', 'id');
    }

    public function assignedAcademicProfile(): EmployeeProfile|FacultyProfile|null
    {
        return $this->employeeProfile ?? $this->facultyProfile;
    }

    public function hasCollegeAssignment(): bool
    {
        return filled($this->assignedAcademicProfile()?->college_id);
    }

    public function hasDepartmentAssignment(): bool
    {
        return filled($this->assignedAcademicProfile()?->department_id);
    }

    public function canAccessCollegeRooms(): bool
    {
        if (! $this->can('rooms.view') || ! $this->hasCollegeAssignment()) {
            return false;
        }

        if ($this->hasRole('collegeAdmin')) {
            return true;
        }

        return $this->hasDirectPermission('rooms.view') && ! $this->hasRole('deptAdmin');
    }

    public function canAccessDepartmentRooms(): bool
    {
        if (! $this->can('rooms.view') || ! $this->hasDepartmentAssignment()) {
            return false;
        }

        if ($this->hasRole('deptAdmin') || $this->hasRole('collegeAdmin')) {
            return true;
        }

        return $this->hasDirectPermission('rooms.view') && ! $this->hasRole('collegeAdmin');
    }

    public function canAccessCollegeFacultyProfiles(): bool
    {
        if (! $this->can('faculty_profiles.view') || ! $this->hasCollegeAssignment()) {
            return false;
        }

        if ($this->hasRole('collegeAdmin')) {
            return true;
        }

        return $this->hasDirectPermission('faculty_profiles.view') && ! $this->hasRole('deptAdmin');
    }

    public function canAccessDepartmentFacultyProfiles(): bool
    {
        if (! $this->can('faculty_profiles.view') || ! $this->hasDepartmentAssignment()) {
            return false;
        }

        if ($this->hasRole('deptAdmin') || $this->hasRole('collegeAdmin')) {
            return true;
        }

        return $this->hasDirectPermission('faculty_profiles.view') && ! $this->hasRole('collegeAdmin');
    }

    public function updatedFacultyProfiles(): HasMany
    {
        return $this->hasMany(FacultyProfile::class, 'updated_by', 'id');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Determine if the user can sign in through Google OAuth.
     */
    public function canUseGoogleSignIn(): bool
    {
        if (! $this->is_active || $this->trashed()) {
            return false;
        }

        return $this->dashboardRoute() !== null;
    }

    /**
     * Resolve the highest priority dashboard route for this user.
     */
    public const DASHBOARD_ACCESS = [
        'dashboard.admin' => 'campuses.view',
        'dashboard.college' => 'departments.view',
        'dashboard.department' => 'schedules.assign',
        'dashboard.faculty' => 'faculty_schedules.view',
    ];

    public function dashboardRoute(): ?string
    {
        foreach (self::DASHBOARD_ACCESS as $route => $permission) {
            if ($this->can($permission)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Persist Google account metadata for the user.
     */
    public function syncGoogleProfile(string $googleId, ?string $avatar): void
    {
        $this->forceFill([
            'google_id' => $googleId,
            'avatar' => $avatar,
            'email_verified_at' => $this->email_verified_at ?? now(),
        ])->save();
    }
}
