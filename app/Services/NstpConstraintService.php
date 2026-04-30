<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class NstpConstraintService
{
    public const CWTS_MAX_SLOTS = 80;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(array $payload): void
    {
        if (($payload['section_type'] ?? null) !== 'NSTP') {
            return;
        }

        $subjectCode = strtoupper((string) ($payload['subject_code'] ?? ''));

        if (str_contains($subjectCode, 'CWTS') && (int) ($payload['slots'] ?? 0) > self::CWTS_MAX_SLOTS) {
            throw ValidationException::withMessages([
                'slots' => ['NSTP-CWTS sections cannot exceed '.self::CWTS_MAX_SLOTS.' slots.'],
            ]);
        }

        $this->enforceRotcContinuity($payload, $subjectCode);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enforceRotcContinuity(array $payload, string $subjectCode): void
    {
        if (! str_contains($subjectCode, 'NSTP2')) {
            return;
        }

        $selectedTrack = strtoupper((string) ($payload['nstp_track'] ?? ''));
        $studentHistories = $payload['student_nstp_histories'] ?? [];

        if (! is_array($studentHistories) || $studentHistories === []) {
            return;
        }

        foreach ($studentHistories as $history) {
            $nstp1Track = strtoupper((string) ($history['nstp1_track'] ?? ''));

            if ($nstp1Track === 'ROTC' && $selectedTrack !== 'ROTC') {
                throw ValidationException::withMessages([
                    'nstp_track' => ['ROTC continuity is required for NSTP2 students who completed ROTC in NSTP1.'],
                ]);
            }
        }
    }
}
