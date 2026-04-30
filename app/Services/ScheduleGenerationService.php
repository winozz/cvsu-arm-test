<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\Schedule;
use App\Models\ScheduleSection;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleGenerationService
{
    public function __construct(
        private readonly ScheduleCodeGenerator $codeGenerator,
        private readonly NstpConstraintService $nstpConstraintService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return Collection<int, Schedule>
     */
    public function generateBlockSchedules(array $payload): Collection
    {
        $curriculum = Curriculum::query()
            ->where('program_id', (int) $payload['program_id'])
            ->orderByDesc('year_implemented')
            ->firstOrFail();

        $entries = $curriculum->entries()
            ->with('subject')
            ->where('year_level', (int) $payload['year_level'])
            ->where('semester', (string) $payload['semester'])
            ->get()
            ->reject(fn ($entry) => $this->isNstpSubject($entry->subject));

        $sectionCount = max(1, (int) $payload['section_count']);
        $programCode = strtoupper((string) $payload['program_code']);
        $createdSchedules = collect();

        DB::transaction(function () use ($entries, $sectionCount, $payload, $programCode, &$createdSchedules): void {
            for ($section = 1; $section <= $sectionCount; $section++) {
                foreach ($entries as $entry) {
                    $schedule = Schedule::query()->create([
                        'sched_code' => $this->codeGenerator->generate((int) $payload['campus_id'], (string) $payload['school_year']),
                        'subject_id' => $entry->subject_id,
                        'campus_id' => (int) $payload['campus_id'],
                        'college_id' => (int) $payload['college_id'],
                        'department_id' => filled($payload['department_id'] ?? null) ? (int) $payload['department_id'] : null,
                        'semester' => (string) $payload['semester'],
                        'school_year' => (string) $payload['school_year'],
                        'slots' => (int) ($payload['slots'] ?? 40),
                        'status' => 'draft',
                    ]);

                    $computedSectionName = sprintf('%s%s-%s', $programCode, (int) $payload['year_level'], $section);

                    ScheduleSection::query()->create([
                        'schedule_id' => $schedule->id,
                        'program_code' => $programCode,
                        'year_level' => (int) $payload['year_level'],
                        'section_identifier' => (string) $section,
                        'section_type' => 'REGULAR',
                        'computed_section_name' => $computedSectionName,
                    ]);

                    $createdSchedules->push($schedule);
                }
            }
        });

        return $createdSchedules;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCustomSectionSchedule(array $payload): Schedule
    {
        $subject = Subject::query()->findOrFail((int) $payload['subject_id']);

        $constraintPayload = [
            'section_type' => $payload['section_type'],
            'subject_code' => $subject->code,
            'slots' => $payload['slots'] ?? 40,
            'nstp_track' => $payload['nstp_track'] ?? null,
            'student_nstp_histories' => $payload['student_nstp_histories'] ?? [],
        ];

        $this->nstpConstraintService->validate($constraintPayload);

        return DB::transaction(function () use ($payload, $subject): Schedule {
            $schedule = Schedule::query()->create([
                'sched_code' => $this->codeGenerator->generate((int) $payload['campus_id'], (string) $payload['school_year']),
                'subject_id' => $subject->id,
                'campus_id' => (int) $payload['campus_id'],
                'college_id' => (int) $payload['college_id'],
                'department_id' => filled($payload['department_id'] ?? null) ? (int) $payload['department_id'] : null,
                'semester' => (string) $payload['semester'],
                'school_year' => (string) $payload['school_year'],
                'slots' => (int) ($payload['slots'] ?? 40),
                'status' => 'draft',
            ]);

            $yearLevel = filled($payload['year_level'] ?? null) ? (int) $payload['year_level'] : null;
            $sectionIdentifier = strtoupper(trim((string) $payload['section_identifier']));
            $computedSectionName = $this->buildSectionName((string) $payload['program_code'], $yearLevel, $sectionIdentifier);

            ScheduleSection::query()->create([
                'schedule_id' => $schedule->id,
                'program_code' => strtoupper((string) $payload['program_code']),
                'year_level' => $yearLevel,
                'section_identifier' => $sectionIdentifier,
                'section_type' => (string) $payload['section_type'],
                'computed_section_name' => $computedSectionName,
            ]);

            return $schedule;
        });
    }

    private function isNstpSubject(?Subject $subject): bool
    {
        if (! $subject) {
            return false;
        }

        $code = strtoupper((string) $subject->code);
        $title = strtoupper((string) $subject->title);

        return str_contains($code, 'NSTP1')
            || str_contains($code, 'NSTP2')
            || str_contains($title, 'NSTP 1')
            || str_contains($title, 'NSTP 2');
    }

    private function buildSectionName(string $programCode, ?int $yearLevel, string $sectionIdentifier): string
    {
        $normalizedProgramCode = strtoupper(trim($programCode));

        if ($normalizedProgramCode === '') {
            throw new InvalidArgumentException('Program code is required.');
        }

        $yearPrefix = $yearLevel ? (string) $yearLevel : '';

        return sprintf('%s%s-%s', $normalizedProgramCode, $yearPrefix, $sectionIdentifier);
    }
}
