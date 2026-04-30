<?php

use App\Livewire\Tables\Admin\ProgramsTable;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function collegeAdminProgramContext(): array
{
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create([
        'code' => 'CAS',
        'name' => 'College of Arts and Sciences',
    ]);
    $department = Department::factory()->forCollege($college)->create([
        'code' => 'CAS-ACAD',
        'name' => 'Academic Programs Department',
    ]);
    $user = User::factory()->collegeAdmin()->create();

    return [$campus, $college, $department, $user];
}

describe('college admin program management', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    });

    it('college admin can create a new shared program and assign it to their college', function () {
        [, $college, , $user] = collegeAdminProgramContext();

        Livewire::actingAs($user)
            ->test('pages::college-admin.programs.index')
            ->call('openCreateProgramModal')
            ->set('programForm.code', 'BSCS')
            ->set('programForm.title', 'Bachelor of Science in Computer Science')
            ->set('programForm.description', 'Shared program record created from the college page.')
            ->set('programForm.no_of_years', 4)
            ->set('programForm.level', 'UNDERGRADUATE')
            ->set('programForm.is_active', true)
            ->call('saveProgram')
            ->assertHasNoErrors();

        $program = Program::query()->where('code', 'BSCS')->first();

        expect($program)->not->toBeNull()
            ->and($college->fresh()->programs->modelKeys())->toContain($program->id);
    });

    it('faculty users with programs view permission can access the programs page using their faculty profile', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create([
            'code' => 'CAS',
            'name' => 'College of Arts and Sciences',
        ]);
        Department::factory()->forCollege($college)->create([
            'code' => 'CAS-ACAD',
            'name' => 'Academic Programs Department',
        ]);

        $user = User::factory()->faculty()->create();
        $user->givePermissionTo('programs.view');

        Livewire::actingAs($user)
            ->test('pages::college-admin.programs.index')
            ->assertSee('Program List')
            ->assertSee($college->code);
    });

    it('faculty users without programs view permission are forbidden from the programs page', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        Department::factory()->forCollege($college)->create();

        $user = User::factory()->faculty()->create();

        $this->actingAs($user)
            ->get(route('programs.index'))
            ->assertRedirect(route('dashboard.resolve'));
    });

    it('faculty users with only programs view permission do not see the create program action', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        Department::factory()->forCollege($college)->create();

        $user = User::factory()->faculty()->create();
        $user->givePermissionTo('programs.view');

        Livewire::actingAs($user)
            ->test('pages::college-admin.programs.index')
            ->assertDontSeeHtml('wire:click="openCreateProgramModal"');
    });

    it('exact duplicate programs are blocked and listed before creation', function () {
        [, $college, , $user] = collegeAdminProgramContext();
        $otherCollege = College::factory()->forCampus($college->campus)->create([
            'code' => 'COE',
            'name' => 'College of Engineering',
        ]);
        $sameCodeProgram = Program::factory()->create([
            'code' => 'BSE',
            'title' => 'Bachelor of Secondary Education',
        ]);
        $sameTitleProgram = Program::factory()->create([
            'code' => 'ENGR',
            'title' => 'Bachelor of Science in Engineering',
        ]);
        $otherCollege->programs()->attach([$sameCodeProgram->id, $sameTitleProgram->id]);

        Livewire::actingAs($user)
            ->test('pages::college-admin.programs.index')
            ->call('openCreateProgramModal')
            ->set('programForm.code', 'BSE')
            ->set('programForm.title', 'Bachelor of Science in Engineering')
            ->set('programForm.description', 'Potential duplicate')
            ->set('programForm.no_of_years', 4)
            ->set('programForm.level', 'UNDERGRADUATE')
            ->set('programForm.is_active', true)
            ->call('confirmSaveProgram')
            ->assertHasNoErrors()
            ->assertSet('programDuplicateConflictType', 'exact')
            ->assertSet('programExactDuplicateConflicts', [
                'BSE - Bachelor of Secondary Education | Colleges: COE',
                'ENGR - Bachelor of Science in Engineering | Colleges: COE',
            ])
            ->assertSet('programModal', true);

        expect(Program::query()->where('code', 'BSE')->count())->toBe(1);
    });

    it('similar program matches are shown for confirmation before creation', function () {
        [, $college, , $user] = collegeAdminProgramContext();
        $otherCollege = College::factory()->forCampus($college->campus)->create([
            'code' => 'COE',
            'name' => 'College of Engineering',
        ]);
        $existingProgram = Program::factory()->create([
            'code' => 'BSCS',
            'title' => 'Bachelor of Science in Computer Science',
        ]);
        $otherCollege->programs()->attach($existingProgram->id);

        Livewire::actingAs($user)
            ->test('pages::college-admin.programs.index')
            ->call('openCreateProgramModal')
            ->set('programForm.code', 'BSCSA')
            ->set('programForm.title', 'Bachelor of Science in Computer Sciences')
            ->set('programForm.description', 'Potential similar duplicate')
            ->set('programForm.no_of_years', 4)
            ->set('programForm.level', 'UNDERGRADUATE')
            ->set('programForm.is_active', true)
            ->call('confirmSaveProgram')
            ->assertHasNoErrors()
            ->assertSet('programDuplicateConflictType', 'similar')
            ->assertSet('programSimilarDuplicateConflicts', [
                'BSCS - Bachelor of Science in Computer Science | Colleges: COE',
            ])
            ->assertSet('programModal', true)
            ->call('proceedWithSimilarProgramCreation')
            ->assertHasNoErrors();

        $program = Program::query()->where('code', 'BSCSA')->first();

        expect($program)->not->toBeNull()
            ->and($college->fresh()->programs->modelKeys())->toContain($program->id);
    });

    it('program code punctuation variants are warned as similar but can still be created', function () {
        [, $college, , $user] = collegeAdminProgramContext();
        $otherCollege = College::factory()->forCampus($college->campus)->create([
            'code' => 'COE',
            'name' => 'College of Engineering',
        ]);
        $existingProgram = Program::factory()->create([
            'code' => 'BSE-S',
            'title' => 'Bachelor of Science in Environmental Science',
        ]);
        $otherCollege->programs()->attach($existingProgram->id);

        Livewire::actingAs($user)
            ->test('pages::college-admin.programs.index')
            ->call('openCreateProgramModal')
            ->set('programForm.code', 'BSES')
            ->set('programForm.title', 'Bachelor of Science in Earth Science')
            ->set('programForm.description', 'Similar code, different shared program.')
            ->set('programForm.no_of_years', 4)
            ->set('programForm.level', 'UNDERGRADUATE')
            ->set('programForm.is_active', true)
            ->call('confirmSaveProgram')
            ->assertHasNoErrors()
            ->assertSet('programDuplicateConflictType', 'similar')
            ->assertSet('programSimilarDuplicateConflicts', [
                'BSE-S - Bachelor of Science in Environmental Science | Colleges: COE',
            ])
            ->call('proceedWithSimilarProgramCreation')
            ->assertHasNoErrors();

        $program = Program::query()->where('code', 'BSES')->first();

        expect($program)->not->toBeNull()
            ->and($college->fresh()->programs->modelKeys())->toContain($program->id);
    });

    it('college admin can edit a shared program from their page', function () {
        [, $college, , $user] = collegeAdminProgramContext();
        $otherCollege = College::factory()->forCampus($college->campus)->create();
        $program = Program::factory()->create([
            'code' => 'BSMATH',
            'title' => 'Bachelor of Science in Mathematics',
        ]);
        $college->programs()->attach($program->id);
        $otherCollege->programs()->attach($program->id);

        Livewire::actingAs($user)
            ->test('pages::college-admin.programs.index')
            ->call('openEditProgramModal', $program->id)
            ->assertSet('sharedProgramCollegeCount', 2)
            ->set('programForm.title', 'Bachelor of Science in Applied Mathematics')
            ->set('programForm.description', 'Updated shared record.')
            ->call('saveProgram')
            ->assertHasNoErrors();

        expect($program->fresh()->title)->toBe('Bachelor of Science in Applied Mathematics')
            ->and($program->fresh()->description)->toBe('Updated shared record.')
            ->and($otherCollege->fresh()->programs->modelKeys())->toContain($program->id);
    });

    it('college admin can offer an existing shared program to their college', function () {
        [, $college, , $user] = collegeAdminProgramContext();
        $otherCollege = College::factory()->forCampus($college->campus)->create();
        $program = Program::factory()->create([
            'code' => 'BSIT',
            'title' => 'Bachelor of Science in Information Technology',
        ]);
        $otherCollege->programs()->attach($program->id);

        Livewire::actingAs($user)
            ->test('pages::college-admin.programs.index')
            ->call('openOfferProgramModal')
            ->set('offeredProgramId', $program->id)
            ->call('offerProgram')
            ->assertHasNoErrors();

        expect($college->fresh()->programs->modelKeys())->toContain($program->id)
            ->and(DB::table('college_programs')->where([
                'college_id' => $otherCollege->id,
                'program_id' => $program->id,
            ])->exists())->toBeTrue();
    });

    it('college admin can remove a program from their offered list without deleting the shared program', function () {
        [, $college, , $user] = collegeAdminProgramContext();
        $otherCollege = College::factory()->forCampus($college->campus)->create();
        $program = Program::factory()->create();
        $college->programs()->attach($program->id);
        $otherCollege->programs()->attach($program->id);

        Livewire::actingAs($user)
            ->test(ProgramsTable::class, ['collegeId' => $college->id])
            ->call('deleteProgram', $program->id)
            ->assertHasNoErrors();

        expect($program->fresh()->trashed())->toBeFalse()
            ->and(DB::table('college_programs')->where([
                'college_id' => $college->id,
                'program_id' => $program->id,
            ])->exists())->toBeFalse()
            ->and(DB::table('college_programs')->where([
                'college_id' => $otherCollege->id,
                'program_id' => $program->id,
            ])->exists())->toBeTrue()
            ->and(Program::query()->whereKey($program->id)->exists())->toBeTrue();
    });
});
