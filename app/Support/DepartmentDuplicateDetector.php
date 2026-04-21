<?php

namespace App\Support;

use App\Models\Department;
use Illuminate\Support\Str;

class DepartmentDuplicateDetector
{
    public static function findPotentialConflicts(int $collegeId, string $code, string $name): array
    {
        return Department::query()
            ->where('college_id', $collegeId)
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (Department $department) => self::looksLike($code, $name, $department))
            ->sortBy(fn (Department $department) => $department->code)
            ->map(fn (Department $department) => e($department->code).' - '.e($department->name))
            ->values()
            ->all();
    }

    public static function looksLike(string $code, string $name, Department $existing): bool
    {
        $enteredCode = self::normalizeCode($code);
        $existingCode = self::normalizeCode($existing->code);
        $enteredName = self::normalizeName($name);
        $existingName = self::normalizeName($existing->name);

        return self::codesLookSimilar($enteredCode, $existingCode)
            || self::namesLookSimilar($enteredName, $existingName);
    }

    public static function codesLookSimilar(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
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

    public static function namesLookSimilar(string $left, string $right): bool
    {
        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
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

    public static function normalizeName(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::squish((string) $value))) ?? '';
    }

    public static function warningMessage(array $conflicts): string
    {
        $items = collect($conflicts)->map(fn (string $conflict) => '&bull; '.$conflict)->implode('<br>');

        return 'There are already existing departments under this college with the same or similar code/name. '
            .'This may cause a conflict or duplicate record.<br><br>'
            .$items
            .'<br><br>Do you want to continue creating this department anyway?';
    }
}
