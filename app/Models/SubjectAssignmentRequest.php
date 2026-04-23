<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'subject_id',
    'request_type',
    'status',
    'source_campus_id',
    'source_college_id',
    'target_campus_id',
    'target_college_id',
    'requested_by',
    'reviewed_by',
    'reviewed_at',
    'cancelled_at',
])]
class SubjectAssignmentRequest extends Model
{
    use HasFactory;

    public const TYPE_ASSIGN = 'assign';

    public const TYPE_TRANSFER = 'transfer';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function requestTypeLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->request_type) {
                self::TYPE_ASSIGN => 'Assign',
                self::TYPE_TRANSFER => 'Transfer',
                default => ucfirst((string) $this->request_type),
            },
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => ucfirst((string) $this->status),
        );
    }

    protected function sourceScopeLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->formatScopeLabel($this->sourceCampus, $this->sourceCollege),
        );
    }

    protected function targetScopeLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->formatScopeLabel($this->targetCampus, $this->targetCollege),
        );
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function sourceCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'source_campus_id');
    }

    public function sourceCollege(): BelongsTo
    {
        return $this->belongsTo(College::class, 'source_college_id');
    }

    public function targetCampus(): BelongsTo
    {
        return $this->belongsTo(Campus::class, 'target_campus_id');
    }

    public function targetCollege(): BelongsTo
    {
        return $this->belongsTo(College::class, 'target_college_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function formatScopeLabel(?Campus $campus, ?College $college): string
    {
        if ($college) {
            return trim($campus?->code.' / '.$college->code, ' /');
        }

        return $campus?->code ?? '-';
    }
}
