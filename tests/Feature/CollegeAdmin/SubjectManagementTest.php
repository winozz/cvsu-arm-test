<?php

use App\Livewire\Tables\CollegeAdmin\SubjectsTable;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

function collegeAdminSubjectContext(): array
{
    $campus = Campus::factory()->create();
    $college = College::factory()->forCampus($campus)->create([
        'code' => 'CAS',
        'name' => 'College of Arts and Sciences',
    ]);
    Department::factory()->forCollege($college)->create([
        'code' => 'CAS-ACAD',
        'name' => 'Academic Programs Department',
    ]);
    $user = User::factory()->collegeAdmin()->create();

    return [$campus, $college, $user];
}

describe('college admin subject management', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    });

    it('college admin can create a new subject', function () {
        [, , $user] = collegeAdminSubjectContext();

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->call('openCreateSubjectModal')
            ->set('subjectForm.code', 'ITEC101')
            ->set('subjectForm.title', 'Introduction to Computing')
            ->set('subjectForm.description', 'Fundamentals of computing.')
            ->set('subjectForm.lecture_units', 3)
            ->set('subjectForm.laboratory_units', 0)
            ->set('subjectForm.is_credit', true)
            ->set('subjectForm.is_active', true)
            ->call('saveSubject')
            ->assertHasNoErrors();

        $subject = Subject::query()->where('code', 'ITEC101')->first();

        expect($subject)->not->toBeNull()
            ->and($subject->title)->toBe('Introduction to Computing')
            ->and($subject->lecture_units)->toBe(3)
            ->and($subject->is_credit)->toBeTrue();
    });

    it('faculty users with subjects view permission can access the subjects page', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create([
            'code' => 'CAS',
        ]);
        Department::factory()->forCollege($college)->create([
            'code' => 'CAS-ACAD',
            'name' => 'Academic Programs Department',
        ]);

        $user = User::factory()->faculty()->create();
        $user->givePermissionTo('subjects.view');

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->assertSee('Subject List')
            ->assertSee($college->code);
    });

    it('faculty users without subjects view permission are forbidden from the subjects page', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        Department::factory()->forCollege($college)->create();

        $user = User::factory()->faculty()->create();

        $this->actingAs($user)
            ->get(route('subjects.index'))
            ->assertRedirect(route('dashboard.resolve'));
    });

    it('users with only subjects view permission do not see the create subject action', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        Department::factory()->forCollege($college)->create();

        $user = User::factory()->faculty()->create();
        $user->givePermissionTo('subjects.view');

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->assertDontSeeHtml('wire:click="openCreateSubjectModal"');
    });

    it('exact duplicate subjects are blocked and listed before creation', function () {
        [, , $user] = collegeAdminSubjectContext();

        $existingSubject = Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
        ]);

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->call('openCreateSubjectModal')
            ->set('subjectForm.code', 'ITEC101')
            ->set('subjectForm.title', 'Completely Different Title')
            ->set('subjectForm.lecture_units', 3)
            ->set('subjectForm.laboratory_units', 0)
            ->set('subjectForm.is_credit', true)
            ->set('subjectForm.is_active', true)
            ->call('confirmSaveSubject')
            ->assertHasNoErrors()
            ->assertSet('subjectDuplicateConflictType', 'exact')
            ->assertSet('subjectExactDuplicateConflicts', [
                'ITEC101 - Introduction to Computing',
            ])
                ->assertSet('subjectModal', true);

        expect(Subject::query()->where('code', 'ITEC101')->count())->toBe(1);
    });

    it('similar subject matches are shown for confirmation before creation', function () {
        [, , $user] = collegeAdminSubjectContext();

        Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
        ]);

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->call('openCreateSubjectModal')
            ->set('subjectForm.code', 'ITEC101A')
            ->set('subjectForm.title', 'Introduction to Computer Applications')
            ->set('subjectForm.lecture_units', 3)
            ->set('subjectForm.laboratory_units', 0)
            ->set('subjectForm.is_credit', true)
            ->set('subjectForm.is_active', true)
            ->call('confirmSaveSubject')
            ->assertHasNoErrors()
            ->assertSet('subjectDuplicateConflictType', 'similar')
                ->assertSet('subjectModal', true)
            ->call('proceedWithSimilarSubjectCreation')
            ->assertHasNoErrors();

        $subject = Subject::query()->where('code', 'ITEC101A')->first();

        expect($subject)->not->toBeNull();
    });

    it('subject code punctuation variants are warned as similar but can still be created', function () {
        [, , $user] = collegeAdminSubjectContext();

        Subject::factory()->create([
            'code' => 'ITEC-101',
            'title' => 'Introduction to Computing',
        ]);

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->call('openCreateSubjectModal')
            ->set('subjectForm.code', 'ITEC101')
            ->set('subjectForm.title', 'Completely Different Subject Title Here')
            ->set('subjectForm.lecture_units', 2)
            ->set('subjectForm.laboratory_units', 1)
            ->set('subjectForm.is_credit', true)
            ->set('subjectForm.is_active', true)
            ->call('confirmSaveSubject')
            ->assertHasNoErrors()
            ->assertSet('subjectDuplicateConflictType', 'similar')
                ->assertSet('subjectModal', true)
            ->call('proceedWithSimilarSubjectCreation')
            ->assertHasNoErrors();

        expect(Subject::query()->where('code', 'ITEC101')->exists())->toBeTrue();
    });

    it('college admin can edit an existing subject', function () {
        [, , $user] = collegeAdminSubjectContext();

        $subject = Subject::factory()->create([
            'code' => 'MATH101',
            'title' => 'Mathematics I',
            'lecture_units' => 3,
            'laboratory_units' => 0,
        ]);

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->call('openEditSubjectModal', $subject->id)
            ->set('subjectForm.title', 'Mathematics I (Revised)')
            ->set('subjectForm.lecture_units', 2)
            ->set('subjectForm.laboratory_units', 1)
            ->call('saveSubject')
            ->assertHasNoErrors();

        expect($subject->fresh()->title)->toBe('Mathematics I (Revised)')
            ->and($subject->fresh()->lecture_units)->toBe(2)
            ->and($subject->fresh()->laboratory_units)->toBe(1);
    });

    it('editing a subject excludes itself from duplicate code checks', function () {
        [, , $user] = collegeAdminSubjectContext();

        $subject = Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
            'lecture_units' => 3,
            'laboratory_units' => 0,
        ]);

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->call('openEditSubjectModal', $subject->id)
            ->set('subjectForm.description', 'Updated description only.')
            ->call('confirmSaveSubject')
            ->assertHasNoErrors()
            ->assertSet('subjectDuplicateConflictType', null);
    });

    it('exact duplicate is blocked on edit when another subject has the same code', function () {
        [, , $user] = collegeAdminSubjectContext();

        Subject::factory()->create(['code' => 'ITEC101', 'title' => 'Introduction to Computing']);
        $subject = Subject::factory()->create(['code' => 'MATH101', 'title' => 'Mathematics I', 'lecture_units' => 3, 'laboratory_units' => 0]);

        Livewire::actingAs($user)
            ->test('pages::college-admin.subjects.index')
            ->call('openEditSubjectModal', $subject->id)
            ->set('subjectForm.code', 'ITEC101')
            ->call('confirmSaveSubject')
            ->assertHasNoErrors()
            ->assertSet('subjectDuplicateConflictType', 'exact')
                ->assertSet('subjectModal', true);

        expect($subject->fresh()->code)->toBe('MATH101');
    });
});

describe('college admin subjects table delete and restore', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    });

    it('college admin can soft delete a subject from the subjects table', function () {
        [, , $user] = collegeAdminSubjectContext();

        $subject = Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
        ]);

        Livewire::actingAs($user)
            ->test(SubjectsTable::class)
            ->call('deleteSubject', $subject->id)
            ->assertHasNoErrors();

        expect($subject->fresh())->not->toBeNull()
            ->and($subject->fresh()->trashed())->toBeTrue();
    });

    it('college admin can restore a trashed subject from the subjects table', function () {
        [, , $user] = collegeAdminSubjectContext();

        $subject = Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
        ]);
        $subject->delete();

        Livewire::actingAs($user)
            ->test(SubjectsTable::class)
            ->call('restoreSubject', $subject->id)
            ->assertHasNoErrors();

        expect($subject->fresh())->not->toBeNull()
            ->and($subject->fresh()->trashed())->toBeFalse();
    });

    it('users without subjects delete permission cannot delete a subject', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        Department::factory()->forCollege($college)->create();

        $user = User::factory()->faculty()->create();
        $user->givePermissionTo('subjects.view');

        $subject = Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
        ]);

        Livewire::actingAs($user)
            ->test(SubjectsTable::class)
            ->call('deleteSubject', $subject->id)
            ->assertForbidden();

        expect($subject->fresh()->trashed())->toBeFalse();
    });

    it('users without subjects restore permission cannot restore a subject', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        Department::factory()->forCollege($college)->create();

        $user = User::factory()->faculty()->create();
        $user->givePermissionTo('subjects.view');

        $subject = Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
        ]);
        $subject->delete();

        Livewire::actingAs($user)
            ->test(SubjectsTable::class)
            ->call('restoreSubject', $subject->id)
            ->assertForbidden();

        expect($subject->fresh()->trashed())->toBeTrue();
    });

    it('active subjects show edit and remove actions for users with update and delete permissions', function () {
        [, , $user] = collegeAdminSubjectContext();

        $subject = Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
        ]);

        $actions = Livewire::actingAs($user)
            ->test(SubjectsTable::class)
            ->instance()
            ->actions($subject);

        $actionIds = collect($actions)->map(fn ($action) => $action->action)->all();

        expect($actionIds)->toContain('edit')
            ->toContain('delete');
    });

    it('trashed subjects show restore action for users with restore permission', function () {
        [, , $user] = collegeAdminSubjectContext();

        $subject = Subject::factory()->create([
            'code' => 'ITEC101',
            'title' => 'Introduction to Computing',
        ]);
        $subject->delete();

        $actions = Livewire::actingAs($user)
            ->test(SubjectsTable::class)
            ->instance()
            ->actions($subject->fresh());

        $actionIds = collect($actions)->map(fn ($action) => $action->action)->all();

        expect($actionIds)->toContain('restore')
            ->not->toContain('edit')
            ->not->toContain('delete');
    });
});
