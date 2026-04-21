<?php

use App\Models\College;
use App\Models\Program;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates the expected college_programs pivot table', function () {
    expect(Schema::hasTable('college_programs'))->toBeTrue()
        ->and(Schema::hasColumns('college_programs', ['college_id', 'program_id', 'created_at', 'updated_at']))->toBeTrue();
});

it('allows colleges and programs to be attached through the college_programs pivot', function () {
    $college = College::factory()->create();
    $program = Program::factory()->create();

    $college->programs()->attach($program->id);

    expect($college->fresh()->programs->modelKeys())->toContain($program->id)
        ->and($program->fresh()->colleges->modelKeys())->toContain($college->id);
});

it('prevents duplicate college-program assignments', function () {
    $college = College::factory()->create();
    $program = Program::factory()->create();

    $college->programs()->attach($program->id);

    expect(fn () => $college->programs()->attach($program->id))
        ->toThrow(QueryException::class);
});

it('removes pivot assignments when a program is force deleted', function () {
    $college = College::factory()->create();
    $program = Program::factory()->create();

    $college->programs()->attach($program->id);
    $program->forceDelete();

    expect(DB::table('college_programs')->count())->toBe(0);
});
