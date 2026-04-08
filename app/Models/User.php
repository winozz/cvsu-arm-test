<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

        return collect(array_keys(self::DASHBOARD_ROUTES))
            ->contains(fn (string $role): bool => $this->hasAccessibleDashboardRole($role));
    }

    /**
     * Resolve the highest priority dashboard route for this user.
     */
    public const DASHBOARD_ROUTES = [
        'superAdmin' => 'admin.dashboard',
        'collegeAdmin' => 'college-admin.dashboard',
        'deptAdmin' => 'department-admin.dashboard',
        'faculty' => 'faculty.dashboard',
    ];

    public function dashboardRoute(): ?string
    {
        foreach (self::DASHBOARD_ROUTES as $role => $route) {
            if ($this->hasAccessibleDashboardRole($role)) {
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

    protected function hasAccessibleDashboardRole(string $role): bool
    {
        if (! $this->hasRole($role)) {
            return false;
        }

        return match ($role) {
            'superAdmin' => true,
            'collegeAdmin', 'deptAdmin' => $this->employeeProfile()->exists(),
            'faculty' => $this->hasFacultySignInProfile(),
            default => false,
        };
    }

    protected function hasFacultySignInProfile(): bool
    {
        $profile = $this->facultyProfile;

        return $profile !== null
            && Str::lower((string) $profile->email) === Str::lower($this->email);
    }
}
