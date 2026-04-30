<?php

use App\Models\Campus;
use App\Services\ScheduleCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates sequential codes per campus and school year prefix', function () {
    $campus = Campus::factory()->create();
    $generator = app(ScheduleCodeGenerator::class);

    $first = $generator->generate($campus->id, '2026-2027');
    $second = $generator->generate($campus->id, '2026-2027');

    $prefix = '26'.str_pad((string) $campus->id, 2, '0', STR_PAD_LEFT);

    expect($first)->toBe($prefix.'00001')
        ->and($second)->toBe($prefix.'00002');
});

it('starts a new sequence when the prefix changes', function () {
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();
    $generator = app(ScheduleCodeGenerator::class);

    $firstA = $generator->generate($campusA->id, '2026-2027');
    $firstB = $generator->generate($campusB->id, '2026-2027');

    expect($firstA)->toEndWith('00001')
        ->and($firstB)->toEndWith('00001')
        ->and($firstA)->not->toBe($firstB);
});
