<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sched_code', 'subject_id', 'campus_id', 'college_id', 'department_id', 'semester', 'school_year', 'slots', 'status'])]
class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        'draft',
        'pending_service_acceptance',
        'pending_plotting',
        'plotted',
        'published',
    ];

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ScheduleSection::class);
    }

    public function roomTimes(): HasMany
    {
        return $this->hasMany(ScheduleRoomTime::class);
    }

    public function facultyAssignments(): HasMany
    {
        return $this->hasMany(ScheduleFaculty::class);
    }

    public function serviceRequests(): BelongsToMany
    {
        return $this->belongsToMany(
            ScheduleServiceRequest::class,
            'schedule_service_request_schedules',
            'schedule_id',
            'service_request_id'
        )->withTimestamps();
    }
}
