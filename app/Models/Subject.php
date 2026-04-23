<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'title',
    'description',
    'lecture_units',
    'laboratory_units',
    'is_credit',
    'is_active',
    'status',
    'created_by',
    'submitted_by',
    'submitted_at',
])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    protected function casts(): array
    {
        return [
            'is_credit' => 'boolean',
            'is_active' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

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

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->status) {
                self::STATUS_DRAFT => 'Draft',
                self::STATUS_SUBMITTED => 'Submitted',
                default => ucfirst((string) $this->status),
            },
        );
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'subject_program')
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(SubjectAssignment::class);
    }

    public function subjectAssignmentRequests(): HasMany
    {
        return $this->hasMany(SubjectAssignmentRequest::class);
    }

    public function subjectUserActions(): HasMany
    {
        return $this->hasMany(SubjectUserAction::class);
    }

    public function curriculumEntries(): HasMany
    {
        return $this->hasMany(CurriculumEntry::class);
    }
}
