<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subject_id', 'user_id', 'action', 'description'])]
class SubjectUserAction extends Model
{
    use HasFactory;

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
