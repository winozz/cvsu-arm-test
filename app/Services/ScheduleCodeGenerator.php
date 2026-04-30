<?php

namespace App\Services;

use App\Models\ScheduleSequence;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ScheduleCodeGenerator
{
    /**
     * Generate a unique code in the format <2-digit year><2-digit campus><5-digit sequence>.
     */
    public function generate(int $campusId, string $schoolYear): string
    {
        $yearPrefix = $this->extractYearPrefix($schoolYear);
        $campusPrefix = str_pad((string) $campusId, 2, '0', STR_PAD_LEFT);
        $prefix = $yearPrefix.$campusPrefix;

        return DB::transaction(function () use ($prefix): string {
            $sequence = ScheduleSequence::query()->firstOrCreate(
                ['prefix' => $prefix],
                ['current_value' => 0]
            );

            $lockedSequence = ScheduleSequence::query()
                ->whereKey($sequence->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedSequence->increment('current_value');

            return $prefix.str_pad((string) $lockedSequence->fresh()->current_value, 5, '0', STR_PAD_LEFT);
        });
    }

    private function extractYearPrefix(string $schoolYear): string
    {
        if (! preg_match('/^(\d{4})\s*[-\/]\s*\d{4}$/', trim($schoolYear), $matches)) {
            throw new InvalidArgumentException('School year must be in YYYY-YYYY format.');
        }

        return substr($matches[1], 2, 2);
    }
}
