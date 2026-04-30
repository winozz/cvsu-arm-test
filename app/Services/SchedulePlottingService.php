<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleFaculty;
use App\Models\ScheduleRoomTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchedulePlottingService
{
    public function __construct(private readonly ScheduleConflictService $conflictService)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function plot(int $scheduleId, array $payload): Schedule
    {
        return DB::transaction(function () use ($scheduleId, $payload): Schedule {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($scheduleId);

            $classType = (string) $payload['class_type'];
            $day = $payload['day'] ?? null;
            $timeIn = $payload['time_in'] ?? null;
            $timeOut = $payload['time_out'] ?? null;
            $roomId = isset($payload['room_id']) ? (int) $payload['room_id'] : null;
            $facultyId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;

            $hasTimeInfo = $day !== null && $timeIn !== null && $timeOut !== null;

            if ($facultyId && $hasTimeInfo && $this->conflictService->hasFacultyConflict($facultyId, $day, $timeIn, $timeOut, $classType, $schedule->id)) {
                throw ValidationException::withMessages([
                    'user_id' => ['Selected faculty is already assigned to another class in the selected block.'],
                ]);
            }

            if ($roomId && $hasTimeInfo && $this->conflictService->hasRoomConflict($roomId, $day, $timeIn, $timeOut, $schedule->id)) {
                throw ValidationException::withMessages([
                    'room_id' => ['Selected room is occupied during the selected block.'],
                ]);
            }

            if ($hasTimeInfo && $roomId) {
                ScheduleRoomTime::query()->updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'class_type' => $classType,
                        'day' => $day,
                    ],
                    [
                        'room_id' => $roomId,
                        'time_in' => $timeIn,
                        'time_out' => $timeOut,
                    ]
                );
            }

            if ($facultyId) {
                ScheduleFaculty::query()->updateOrCreate(
                    [
                        'schedule_id' => $schedule->id,
                        'class_type' => $classType,
                    ],
                    [
                        'user_id' => $facultyId,
                    ]
                );
            }

            $schedule->status = 'plotted';
            $schedule->save();

            return $schedule->fresh(['roomTimes', 'facultyAssignments']);
        });
    }
}
