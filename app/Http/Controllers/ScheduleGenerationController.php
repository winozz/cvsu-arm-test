<?php

namespace App\Http\Controllers;

use App\Services\ScheduleGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleGenerationController extends Controller
{
    public function __construct(private readonly ScheduleGenerationService $generationService)
    {
    }

    public function bulkGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'college_id' => ['required', 'integer', 'exists:colleges,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'program_code' => ['required', 'string', 'max:50'],
            'year_level' => ['required', 'integer', 'min:1', 'max:10'],
            'semester' => ['required', Rule::in(array_keys(\App\Models\CurriculumEntry::SEMESTERS))],
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'section_count' => ['required', 'integer', 'min:1', 'max:30'],
            'slots' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $schedules = $this->generationService->generateBlockSchedules($validated)->load('sections');

        return response()->json([
            'message' => 'Schedules generated successfully.',
            'generated_count' => $schedules->count(),
            'schedules' => $schedules,
        ], 201);
    }

    public function createCustomSection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campus_id' => ['required', 'integer', 'exists:campuses,id'],
            'college_id' => ['required', 'integer', 'exists:colleges,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'program_code' => ['required', 'string', 'max:50'],
            'year_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'section_identifier' => ['required', 'string', 'max:80'],
            'section_type' => ['required', Rule::in(['IRREGULAR', 'PETITION', 'NSTP', 'OTHERS'])],
            'semester' => ['required', Rule::in(array_keys(\App\Models\CurriculumEntry::SEMESTERS))],
            'school_year' => ['required', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'slots' => ['nullable', 'integer', 'min:1', 'max:500'],
            'nstp_track' => ['nullable', Rule::in(['CWTS', 'ROTC'])],
            'student_nstp_histories' => ['nullable', 'array'],
            'student_nstp_histories.*.student_id' => ['nullable', 'integer'],
            'student_nstp_histories.*.nstp1_track' => ['nullable', 'string', Rule::in(['CWTS', 'ROTC'])],
        ]);

        $schedule = $this->generationService->createCustomSectionSchedule($validated)
            ->load(['sections', 'subject']);

        return response()->json([
            'message' => 'Custom section schedule created successfully.',
            'schedule' => $schedule,
        ], 201);
    }
}
