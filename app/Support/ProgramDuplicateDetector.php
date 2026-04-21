<?php

namespace App\Support;

use App\Models\College;
use App\Models\Program;
use Illuminate\Support\Str;

class ProgramDuplicateDetector
{
    public static function findConflicts(?Program $excluding, string $code, string $title): array
    {
        $exact = [];
        $similar = [];

        Program::query()
            ->withTrashed()
            ->with(['colleges' => fn ($query) => $query->orderBy('code')])
            ->when($excluding, fn ($query) => $query->whereKeyNot($excluding->id))
            ->get()
            ->each(function (Program $program) use ($code, $title, &$exact, &$similar): void {
                $reasons = self::conflictReasons($code, $title, $program);

                if ($reasons['exact'] !== []) {
                    $exact[] = self::formatConflict($program);

                    return;
                }

                if ($reasons['similar'] !== []) {
                    $similar[] = self::formatConflict($program);
                }
            });

        sort($exact);
        sort($similar);

        return ['exact' => $exact, 'similar' => $similar];
    }

    public static function conflictReasons(string $code, string $title, Program $program): array
    {
        $enteredCode = self::normalizeCode($code);
        $existingCode = self::normalizeCode($program->code);
        $enteredTitle = self::normalizeTitle($title);
        $existingTitle = self::normalizeTitle($program->title);

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

    public static function formatConflict(Program $program): string
    {
        $collegeList = $program->colleges
            ->map(fn (College $college) => e($college->code))
            ->values()
            ->implode(', ');

        $suffix = $program->trashed() ? ' [Trashed]' : '';

        return '<b>'.e($program->code).'</b> - '.e($program->title)
            .' | <b>Colleges: </b>'.($collegeList !== '' ? $collegeList : 'Not assigned.')
            .$suffix;
    }

    public static function exactWarningMessage(array $exactConflicts, array $similarConflicts = []): string
    {
        $exactItems = collect($exactConflicts)->map(fn (string $conflict) => '&bull; '.$conflict)->implode('<br>');
        $message = 'A program with the exact same code or title already exists in the catalog. Creation was stopped to avoid duplicate shared records.<br><br>'
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

        return 'There are existing programs with similar code or title across colleges. Please review these possible duplicates before creating a new shared program.<br><br>'
            .$items
            .'<br><br>Do you want to continue creating this program anyway?';
    }
}
