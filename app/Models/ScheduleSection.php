<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['schedule_id', 'program_code', 'year_level', 'section_identifier', 'section_type', 'computed_section_name'])]
class ScheduleSection extends Model
{
    use HasFactory;

    protected $table = 'schedule_section';

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
