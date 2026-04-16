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

        return collect(array_keys(self::DASHBOARD_ACCESS))
            ->contains(fn (string $route): bool => $this->hasAccessibleDashboardRoute($route));
    }

    /**
     * Resolve the highest priority dashboard route for this user.
     */
    public const DASHBOARD_ACCESS = [
        'admin.dashboard' => 'campuses.view',
        'college-admin.dashboard' => 'departments.view',
        'department-admin.dashboard' => 'schedules.assign',
        'faculty.dashboard' => 'faculty_schedules.view',
    ];

    public function dashboardRoute(): ?string
    {
        foreach (array_keys(self::DASHBOARD_ACCESS) as $route) {
            if ($this->hasAccessibleDashboardRoute($route)) {
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

    protected function hasAccessibleDashboardRoute(string $route): bool
    {
        $permission = self::DASHBOARD_ACCESS[$route] ?? null;

        if (! $permission || ! $this->can($permission)) {
            return false;
        }

        return match ($route) {
            'admin.dashboard' => true,
            'college-admin.dashboard', 'department-admin.dashboard' => $this->employeeProfile()->exists(),
            'faculty.dashboard' => $this->hasFacultySignInProfile(),
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
