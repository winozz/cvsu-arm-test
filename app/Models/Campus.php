<?php

namespace App\Models;

use Database\Factories\CampusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'description', 'is_active'])]
class Campus extends Model
{
    /** @use HasFactory<CampusFactory> */
    use HasFactory, SoftDeletes;

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => (bool) $value,
        );
    }

    public function colleges(): HasMany
    {
        return $this->hasMany(College::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function facultyProfiles(): HasMany
    {
        return $this->hasMany(FacultyProfile::class);
    }

    public function employeeProfiles(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }
}
