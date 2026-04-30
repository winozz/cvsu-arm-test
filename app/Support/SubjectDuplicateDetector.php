<?php

namespace App\Support;

use App\Models\Subject;
use Illuminate\Support\Str;

class SubjectDuplicateDetector
{
    public static function findConflicts(?Subject $excluding, string $code, string $title): array
    {
        $exact = [];
        $similar = [];

        Subject::query()
            ->withTrashed()
            ->when($excluding, fn ($query) => $query->whereKeyNot($excluding->id))
            ->get()
            ->each(function (Subject $subject) use ($code, $title, &$exact, &$similar): void {
                $reasons = self::conflictReasons($code, $title, $subject);

                if ($reasons['exact'] !== []) {
                    $exact[] = self::formatConflict($subject);

                    return;
                }

                if ($reasons['similar'] !== []) {
                    $similar[] = self::formatConflict($subject);
                }
            });

        sort($exact);
        sort($similar);

        return ['exact' => $exact, 'similar' => $similar];
    }

    public static function conflictReasons(string $code, string $title, Subject $subject): array
    {
        $enteredExactCode = self::normalizeExactValue($code);
        $existingExactCode = self::normalizeExactValue($subject->code);

        $exact = [];
        $similar = [];

        if (self::valuesMatchExactly($enteredExactCode, $existingExactCode)) {
            $exact[] = 'exact code';
        } elseif (self::codesMatchFamily($code, $subject->code)) {
            $similar[] = 'similar code';
        }

        return ['exact' => $exact, 'similar' => $similar];
    }

    public static function valuesMatchExactly(string $left, string $right): bool
    {
        return $left !== '' && $left === $right;
    }

    public static function codesMatchFamily(string $left, string $right): bool
    {
        $leftFamily = self::extractCodeFamily($left);
        $rightFamily = self::extractCodeFamily($right);

        return $leftFamily !== '' && $leftFamily === $rightFamily;
    }

    public static function extractCodeFamily(string $code): string
    {
        $normalized = self::normalizeCode($code);
        preg_match('/^([a-z]+)/', $normalized, $matches);

        return $matches[1] ?? '';
    }

    public static function normalizeCode(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(trim((string) $value))) ?? '';
    }

    public static function normalizeExactValue(?string $value): string
    {
        return Str::lower(Str::squish(trim((string) $value)));
    }

    public static function normalizeTitle(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::squish((string) $value))) ?? '';
    }

    public static function formatConflict(Subject $subject): string
    {
        $suffix = $subject->trashed() ? ' [Trashed]' : '';

        return $subject->code.' - '.$subject->title.$suffix;
    }

    public static function exactWarningMessage(array $exactConflicts, array $similarConflicts = []): string
    {
        $exactItems = collect($exactConflicts)->map(fn (string $conflict) => '&bull; '.e($conflict))->implode('<br>');
        $message = 'A subject with the exact same code already exists in the catalog. Creation was stopped to avoid duplicate shared records.<br><br>'
            .'<b>Possible exact duplicates:</b><br>'.$exactItems;

        if ($similarConflicts !== []) {
            $similarItems = collect($similarConflicts)->map(fn (string $conflict) => '&bull; '.e($conflict))->implode('<br>');
            $message .= '<br><br><b>Other similar matches:</b><br>'.$similarItems;
        }

        return $message.'<br><br>If this is already the shared subject you need, assign it to a curriculum instead of creating another record.';
    }

    public static function similarWarningMessage(array $similarConflicts): string
    {
        $items = collect($similarConflicts)->map(fn (string $conflict) => '&bull; '.e($conflict))->implode('<br>');

        return 'There are existing subjects with the same code family in the catalog. Please review these possible duplicates before creating a new subject.<br><br>'
            .$items
            .'<br><br>Do you want to continue creating this subject anyway?';
    }
}
