<?php

namespace App\Models;

use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'employee_profiles';

    protected $fillable = [
        'user_id',
        'employee_no',
        'first_name',
        'middle_name',
        'last_name',
        'position',
        'campus_id',
        'college_id',
        'department_id',
    ];

    /**
     * Get the user that owns the Employee profile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => collect([$this->first_name, $this->middle_name, $this->last_name])
                ->filter(fn (?string $value) => filled($value))
                ->implode(' '),
        );
    }

    protected function shortName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => collect([$this->first_name, $this->last_name])
                ->filter(fn (?string $value) => filled($value))
                ->implode(' '),
        );
    }

    protected function initials(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Str::of($this->full_name)
                ->explode(' ')
                ->filter()
                ->take(2)
                ->map(fn (string $word) => Str::substr($word, 0, 1))
                ->implode(''),
        );
    }
}
