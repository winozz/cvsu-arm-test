<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Collection;
use RuntimeException;

class CvsuSeedData
{
    private const DEPARTMENT_TEMPLATES = [
        ['code_suffix' => 'ACAD', 'name' => 'Academic Programs Department'],
        ['code_suffix' => 'STUD', 'name' => 'Student Services Department'],
        ['code_suffix' => 'REXT', 'name' => 'Research and Extension Department'],
        ['code_suffix' => 'QASS', 'name' => 'Quality Assurance Department'],
        ['code_suffix' => 'ADMS', 'name' => 'Administrative Services Department'],
    ];

    /**
     * @return Collection<int, array{legacy_id: int, name: string, code: string, description: ?string}>
     */
    public static function campuses(): Collection
    {
        return self::readCsv('campuses.csv')->map(
            fn (array $row): array => [
                'legacy_id' => (int) $row['id'],
                'name' => $row['name'],
                'code' => $row['code'],
                'description' => self::nullable($row['description'] ?? null),
            ]
        );
    }

    /**
     * @return Collection<int, array{legacy_id: int, campus_legacy_id: int, name: string, code: string, description: ?string}>
     */
    public static function colleges(): Collection
    {
        return self::readCsv('colleges.csv')->map(
            fn (array $row): array => [
                'legacy_id' => (int) $row['id'],
                'campus_legacy_id' => (int) $row['campus_id'],
                'name' => $row['name'],
                'code' => $row['code'],
                'description' => self::nullable($row['description'] ?? null),
            ]
        );
    }

    /**
     * @return Collection<int, array{campus_id: int, college_code: string, name: string, code: string, description: string}>
     */
    public static function departments(): Collection
    {
        return self::colleges()->flatMap(
            function (array $college): Collection {
                return collect(self::DEPARTMENT_TEMPLATES)->map(
                    fn (array $template): array => [
                        'campus_id' => $college['campus_legacy_id'],
                        'college_code' => $college['code'],
                        'name' => $template['name'],
                        'code' => sprintf('%s-%s', $college['code'], $template['code_suffix']),
                        'description' => sprintf('%s - %s', $college['name'], $template['name']),
                    ]
                );
            }
        );
    }

    /**
     * @return Collection<int, array<string, string>>
     */
    private static function readCsv(string $fileName): Collection
    {
        $path = database_path('data/'.$fileName);
        $rows = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($rows === false || $rows === []) {
            throw new RuntimeException("Seed data file [{$fileName}] is missing or empty.");
        }

        $header = str_getcsv(array_shift($rows));

        return collect($rows)->map(
            static fn (string $row): array => array_combine($header, str_getcsv($row))
        );
    }

    private static function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' || strtoupper($normalized) === 'NULL'
            ? null
            : $normalized;
    }
}
