<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'title', 'description', 'lecture_units', 'laboratory_units', 'is_credit', 'is_active'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, SoftDeletes;

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => (bool) $value,
        );
    }

    protected function isCredit(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => (bool) $value,
        );
    }

    protected function unitsLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => sprintf(
                '%d Lec / %d Lab',
                (int) $this->lecture_units,
                (int) $this->laboratory_units
            ),
        );
    }

    protected function creditLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->is_credit ? 'Credit' : 'Non-credit',
        );
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: fn (): string => trim($this->code.' - '.$this->title, ' -'),
        );
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'subject_program')
            ->withTimestamps();
    }

    public function curriculumEntries(): HasMany
    {
        return $this->hasMany(CurriculumEntry::class);
    }
}
