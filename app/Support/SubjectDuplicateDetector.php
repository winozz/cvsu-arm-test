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
            ->with(['subjectAssignments.campus', 'subjectAssignments.college'])
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
        $enteredCode = self::normalizeCode($code);
        $existingCode = self::normalizeCode($subject->code);
        $enteredTitle = self::normalizeTitle($title);
        $existingTitle = self::normalizeTitle($subject->title);

        $exact = [];
        $similar = [];

        if (self::valuesMatchExactly($enteredCode, $existingCode)) {
            $exact[] = 'exact code';
        } elseif (self::codesLookSimilar($enteredCode, $existingCode)) {
            $similar[] = 'similar code';
        }

        if (self::valuesMatchExactly($enteredTitle, $existingTitle)) {
            $exact[] = 'exact title';
        } elseif (self::titlesLookSimilar($enteredTitle, $existingTitle)) {
            $similar[] = 'similar title';
        }

        return ['exact' => $exact, 'similar' => $similar];
    }

    public static function valuesMatchExactly(string $left, string $right): bool
    {
        return $left !== '' && $left === $right;
    }

    public static function codesLookSimilar(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if (str_contains($left, $right) || str_contains($right, $left)) {
            return true;
        }

        if (levenshtein($left, $right) <= 2) {
            return true;
        }

        similar_text($left, $right, $percent);

        return $percent / 100 >= 0.8;
    }

    public static function titlesLookSimilar(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if (str_contains($left, $right) || str_contains($right, $left)) {
            return true;
        }

        similar_text($left, $right, $percent);

        return $percent / 100 >= 0.9;
    }

    public static function normalizeCode(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', Str::lower(trim((string) $value))) ?? '';
    }

    public static function normalizeTitle(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::squish((string) $value))) ?? '';
    }

    public static function formatConflict(Subject $subject): string
    {
        $scopeList = $subject->subjectAssignments
            ->map(function ($assignment): string {
                if ($assignment->college) {
                    return trim($assignment->campus?->code.' / '.$assignment->college->code, ' /');
                }

                return $assignment->campus?->code ?? 'Unknown scope';
            })
            ->unique()
            ->values()
            ->implode(', ');

        $status = $subject->status === Subject::STATUS_DRAFT ? 'Draft' : 'Submitted';
        $trashed = $subject->trashed() ? ' [Trashed]' : '';

        return '<b>'.e($subject->code).'</b> - '.e($subject->title)
            .' | <b>Scopes:</b> '.($scopeList !== '' ? $scopeList : 'Not assigned')
            .' | <b>Status:</b> '.$status
            .$trashed;
    }

    public static function exactWarningMessage(array $exactConflicts, array $similarConflicts = []): string
    {
        $exactItems = collect($exactConflicts)->map(fn (string $conflict) => '&bull; '.$conflict)->implode('<br>');
        $message = 'A subject with the exact same code or title already exists in the catalog. Submission was stopped to avoid duplicate shared records.<br><br>'
            .'<b>Possible exact duplicates:</b><br>'.$exactItems;

        if ($similarConflicts !== []) {
            $similarItems = collect($similarConflicts)->map(fn (string $conflict) => '&bull; '.$conflict)->implode('<br>');
            $message .= '<br><br><b>Other similar matches:</b><br>'.$similarItems;
        }

        return $message.'<br><br>Review the existing records before trying again.';
    }

    public static function similarWarningMessage(array $similarConflicts): string
    {
        $items = collect($similarConflicts)->map(fn (string $conflict) => '&bull; '.$conflict)->implode('<br>');

        return 'There are existing subjects with similar code or title. Please review these possible duplicates before submitting this subject.<br><br>'
            .$items
            .'<br><br>Do you want to continue submitting this subject anyway?';
    }
}
