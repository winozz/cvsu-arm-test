<?php

namespace App\Http\Controllers;

use App\Services\ScheduleConflictService;
use App\Services\SchedulePlottingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SchedulePlottingController extends Controller
{
    public function __construct(
        private readonly SchedulePlottingService $plottingService,
        private readonly ScheduleConflictService $conflictService,
    ) {
    }

    public function plot(Request $request, int $scheduleId): JsonResponse
    {
        $validated = $request->validate([
            'class_type' => ['required', Rule::in(['LEC', 'LAB', 'CLINIC', 'OTHERS'])],
            'day' => ['required', Rule::in(['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'])],
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['required', 'date_format:H:i', 'after:time_in'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $schedule = $this->plottingService->plot($scheduleId, $validated);

        return response()->json([
            'message' => 'Schedule plotted successfully.',
            'schedule' => $schedule,
        ]);
    }

    public function unavailableFacultySlots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'faculty_id' => ['required', 'integer', 'exists:users,id'],
            'day' => ['required', Rule::in(['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'])],
            'candidate_slots' => ['required', 'array', 'min:1'],
            'candidate_slots.*.time_in' => ['required', 'date_format:H:i'],
            'candidate_slots.*.time_out' => ['required', 'date_format:H:i'],
            'ignore_schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
        ]);

        foreach ($validated['candidate_slots'] as $slot) {
            if ((string) $slot['time_out'] <= (string) $slot['time_in']) {
                throw ValidationException::withMessages([
                    'candidate_slots' => ['Each candidate slot must have time_out later than time_in.'],
                ]);
            }
        }

        $unavailable = $this->conflictService->unavailableFacultySlots(
            (int) $validated['faculty_id'],
            (string) $validated['day'],
            $validated['candidate_slots'],
            isset($validated['ignore_schedule_id']) ? (int) $validated['ignore_schedule_id'] : null,
        );

        return response()->json([
            'unavailable_slots' => $unavailable,
        ]);
    }

    public function unavailableRooms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'day' => ['required', Rule::in(['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'])],
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['required', 'date_format:H:i', 'after:time_in'],
            'ignore_schedule_id' => ['nullable', 'integer', 'exists:schedules,id'],
        ]);

        $unavailableRoomIds = $this->conflictService->unavailableRoomIds(
            (int) $validated['campus_id'],
            (string) $validated['day'],
            (string) $validated['time_in'],
            (string) $validated['time_out'],
            isset($validated['ignore_schedule_id']) ? (int) $validated['ignore_schedule_id'] : null,
        );

        return response()->json([
            'unavailable_room_ids' => $unavailableRoomIds,
        ]);
    }
}
