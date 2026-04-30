<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleServiceRequestSchedule extends Model
{
    protected $table = 'schedule_service_request_schedules';

    protected $fillable = ['service_request_id', 'schedule_id'];

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ScheduleServiceRequest::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
