<?php

use App\Models\Subject;
use App\Support\SubjectDuplicateDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('SubjectDuplicateDetector', function () {
    describe('normalization', function () {
        it('normalizes code by stripping non-alphanumeric characters and lowercasing', function () {
            expect(SubjectDuplicateDetector::normalizeCode('ITEC-101'))
                ->toBe('itec101')
                ->and(SubjectDuplicateDetector::normalizeCode('  MATH 10 '))
                ->toBe('math10');
        });

        it('normalizes exact value by squishing whitespace and lowercasing', function () {
            expect(SubjectDuplicateDetector::normalizeExactValue('  ITEC 101  '))
                ->toBe('itec 101')
                ->and(SubjectDuplicateDetector::normalizeExactValue(null))
                ->toBe('');
        });

        it('extracts code family prefix correctly', function () {
            expect(SubjectDuplicateDetector::extractCodeFamily('ITEC101'))
                ->toBe('itec')
                ->and(SubjectDuplicateDetector::extractCodeFamily('ITEC-101'))
                ->toBe('itec')
                ->and(SubjectDuplicateDetector::extractCodeFamily('ABEN70'))
                ->toBe('aben')
                ->and(SubjectDuplicateDetector::extractCodeFamily('ABC'))
                ->toBe('abc')
                ->and(SubjectDuplicateDetector::extractCodeFamily('123'))
                ->toBe('');
        });
    });

    describe('exact match detection', function () {
        it('detects exact code match', function () {
            $subject = Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'ITEC101', 'Different Title');

            expect($conflicts['exact'])->toHaveCount(1)
                ->and($conflicts['similar'])->toBeEmpty();
        });

        it('does not flag title match when code is from different family', function () {
            $subject = Subject::factory()->create(['code' => 'MATH101', 'title' => 'Introduction to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'ENG201', 'Introduction to Computing');

            expect($conflicts['exact'])->toBeEmpty()
                ->and($conflicts['similar'])->toBeEmpty();
        });

        it('blocks exact code match regardless of letter case', function () {
            Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'itec101', 'Completely Different Title');

            expect($conflicts['exact'])->toHaveCount(1);
        });

        it('does not block when title matches but code is different', function () {
            Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'MATH201', 'Introduction to Computing');

            expect($conflicts['exact'])->toBeEmpty();
        });

        it('excludes the current subject from conflict checks during edit', function () {
            $subject = Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts($subject, 'ITEC101', 'Introduction to Computing');

            expect($conflicts['exact'])->toBeEmpty()
                ->and($conflicts['similar'])->toBeEmpty();
        });

        it('includes trashed subjects in exact conflict check', function () {
            $subject = Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);
            $subject->delete();

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'ITEC101', 'Different Title');

            expect($conflicts['exact'])->toHaveCount(1)
                ->and($conflicts['exact'][0])->toContain('[Trashed]');
        });
    });

    describe('similar match detection', function () {
        it('detects near-code warning for ITEC101 vs ITEC101A', function () {
            Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'ITEC101A', 'Completely Different Title');

            expect($conflicts['exact'])->toBeEmpty()
                ->and($conflicts['similar'])->toHaveCount(1);
        });

        it('warns on punctuation normalization similarity', function () {
            Subject::factory()->create(['code' => 'ITEC-101', 'title' => 'Introduction to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'ITEC101', 'Completely Different Title');

            expect($conflicts['exact'])->toBeEmpty()
                ->and($conflicts['similar'])->toHaveCount(1);
        });

        it('detects code family match for ITEC101 vs ITEC105', function () {
            Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Intro to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'ITEC105', 'Advanced Computing');

            expect($conflicts['exact'])->toBeEmpty()
                ->and($conflicts['similar'])->toHaveCount(1);
        });

        it('detects code family match for ABEN70 vs ABEN100', function () {
            Subject::factory()->create(['code' => 'ABEN70', 'title' => 'Subject A']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'ABEN100', 'Subject B');

            expect($conflicts['exact'])->toBeEmpty()
                ->and($conflicts['similar'])->toHaveCount(1);
        });

        it('does not warn on completely different code families', function () {
            Subject::factory()->create(['code' => 'MATH101', 'title' => 'Calculus I']);

            $conflicts = SubjectDuplicateDetector::findConflicts(null, 'ENG201', 'Technical Writing');

            expect($conflicts['exact'])->toBeEmpty()
                ->and($conflicts['similar'])->toBeEmpty();
        });

        it('excludes the current subject from similar checks during edit', function () {
            $subject = Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);

            $conflicts = SubjectDuplicateDetector::findConflicts($subject, 'ITEC105', 'Advanced Computing');

            expect($conflicts['exact'])->toBeEmpty()
                ->and($conflicts['similar'])->toBeEmpty();
        });
    });

    describe('conflict formatting', function () {
        it('formats a non-trashed subject conflict correctly', function () {
            $subject = Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);

            $formatted = SubjectDuplicateDetector::formatConflict($subject);

            expect($formatted)->toBe('ITEC101 - Introduction to Computing');
        });

        it('appends [Trashed] suffix for trashed subjects', function () {
            $subject = Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);
            $subject->delete();

            $formatted = SubjectDuplicateDetector::formatConflict($subject->fresh());

            expect($formatted)->toBe('ITEC101 - Introduction to Computing [Trashed]');
        });
    });

    describe('warning messages', function () {
        it('exact warning message contains duplicate list', function () {
            $message = SubjectDuplicateDetector::exactWarningMessage(['ITEC101 - Intro']);

            expect($message)->toContain('ITEC101 - Intro')
                ->toContain('Possible exact duplicates');
        });

        it('exact warning message includes similar conflicts when present', function () {
            $message = SubjectDuplicateDetector::exactWarningMessage(['ITEC101 - Intro'], ['ITEC102 - Other']);

            expect($message)->toContain('Other similar matches')
                ->toContain('ITEC102 - Other');
        });

        it('similar warning message contains conflict list and mentions code family', function () {
            $message = SubjectDuplicateDetector::similarWarningMessage(['ITEC101 - Intro']);

            expect($message)->toContain('ITEC101 - Intro')
                ->toContain('code family')
                ->toContain('continue creating this subject anyway');
        });
    });
});
