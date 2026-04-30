<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ScheduleConflictService
{
    public function hasFacultyConflict(
        int $facultyId,
        string $day,
        string $timeIn,
        string $timeOut,
        ?string $classType = null,
        ?int $ignoreScheduleId = null,
    ): bool {
        $query = DB::table('schedule_room_time as srt')
            ->join('schedule_faculty as sf', 'sf.schedule_id', '=', 'srt.schedule_id')
            ->where('sf.user_id', $facultyId)
            ->where('srt.day', $day)
            ->whereNotNull('srt.time_in')
            ->whereNotNull('srt.time_out')
            ->whereRaw('? < srt.time_out', [$timeIn])
            ->whereRaw('? > srt.time_in', [$timeOut]);

        if ($classType !== null) {
            $query->where('sf.class_type', $classType)
                ->whereColumn('sf.class_type', 'srt.class_type');
        }

        if ($ignoreScheduleId !== null) {
            $query->where('srt.schedule_id', '!=', $ignoreScheduleId);
        }

        return $query->exists();
    }

    public function hasRoomConflict(
        int $roomId,
        string $day,
        string $timeIn,
        string $timeOut,
        ?int $ignoreScheduleId = null,
    ): bool {
        $query = DB::table('schedule_room_time')
            ->where('room_id', $roomId)
            ->where('day', $day)
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->whereRaw('? < time_out', [$timeIn])
            ->whereRaw('? > time_in', [$timeOut]);

        if ($ignoreScheduleId !== null) {
            $query->where('schedule_id', '!=', $ignoreScheduleId);
        }

        return $query->exists();
    }

    /**
     * @param  array<int, array{time_in: string, time_out: string}>  $candidateSlots
     * @return array<int, array{time_in: string, time_out: string}>
     */
    public function unavailableFacultySlots(int $facultyId, string $day, array $candidateSlots, ?int $ignoreScheduleId = null): array
    {
        $unavailable = [];

        foreach ($candidateSlots as $slot) {
            if ($this->hasFacultyConflict($facultyId, $day, $slot['time_in'], $slot['time_out'], null, $ignoreScheduleId)) {
                $unavailable[] = $slot;
            }
        }

        return $unavailable;
    }

    /**
     * @return array<int>
     */
    public function unavailableRoomIds(int $campusId, string $day, string $timeIn, string $timeOut, ?int $ignoreScheduleId = null): array
    {
        $occupiedQuery = DB::table('schedule_room_time')
            ->where('day', $day)
            ->whereNotNull('room_id')
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->whereRaw('? < time_out', [$timeIn])
            ->whereRaw('? > time_in', [$timeOut]);

        if ($ignoreScheduleId !== null) {
            $occupiedQuery->where('schedule_id', '!=', $ignoreScheduleId);
        }

        $occupied = $occupiedQuery
            ->pluck('room_id')
            ->filter()
            ->map(fn ($roomId) => (int) $roomId)
            ->values()
            ->all();

        if ($occupied === []) {
            return [];
        }

        return DB::table('rooms')
            ->where('campus_id', $campusId)
            ->whereIn('id', $occupied)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
