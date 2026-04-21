<?php

namespace App\Models;

use Database\Factories\CollegeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'description', 'campus_id', 'is_active'])]
class College extends Model
{
    /** @use HasFactory<CollegeFactory> */
    use HasFactory, SoftDeletes;

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => (bool) $value,
        );
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'college_programs')
            ->withTimestamps();
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
