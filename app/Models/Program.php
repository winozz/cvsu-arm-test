<?php

namespace App\Models;

use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['code', 'title', 'description', 'no_of_years', 'level', 'is_active'])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory, SoftDeletes;

    public const LEVELS = [
        'UNDERGRADUATE' => 'Undergraduate',
        'GRADUATE' => 'Graduate',
        'PRE-BACCALAUREATE' => 'Pre-Baccalaureate',
        'POST-BACCALAUREATE' => 'Post-Baccalaureate',
    ];

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => (bool) $value,
        );
    }

    protected function levelLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => self::LEVELS[$this->level] ?? ($this->level ?: '-'),
        );
    }

    protected function durationLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => filled($this->no_of_years) ? $this->no_of_years.' '.Str::plural('year', $this->no_of_years) : '-',
        );
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->code.' - '.$this->title, ' -'),
        );
    }

    public function colleges(): BelongsToMany
    {
        return $this->belongsToMany(College::class, 'college_programs')
            ->withTimestamps();
    }
}
