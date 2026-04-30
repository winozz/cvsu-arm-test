<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['requesting_college_id', 'servicing_college_id', 'status', 'assigned_department_id'])]
class ScheduleServiceRequest extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending',
        'accepted',
        'rejected',
        'assigned_to_dept',
        'dept_submitted',
        'completed',
        'cancelled',
    ];

    /** Schedules included in this inter-college service request. */
    public function schedules(): BelongsToMany
    {
        return $this->belongsToMany(
            Schedule::class,
            'schedule_service_request_schedules',
            'service_request_id',
            'schedule_id'
        )->withTimestamps();
    }

    public function requestingCollege(): BelongsTo
    {
        return $this->belongsTo(College::class, 'requesting_college_id');
    }

    public function servicingCollege(): BelongsTo
    {
        return $this->belongsTo(College::class, 'servicing_college_id');
    }

    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }
}
