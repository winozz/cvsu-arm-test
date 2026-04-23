<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subject_id', 'campus_id', 'college_id'])]
class SubjectAssignment extends Model
{
    use HasFactory;

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }
}
