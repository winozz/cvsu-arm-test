<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

describe('college admin faculty profiles management', function () {
    beforeEach(function () {
        ensureRoles(['faculty', 'collegeAdmin']);

        $this->user = actingUserWithPermissions([
            'faculty_profiles.view',
            'faculty_profiles.create',
            'faculty_profiles.update',
        ], ['collegeAdmin']);

        $this->campus = Campus::factory()->create();
        $this->college = College::factory()->forCampus($this->campus)->create();
        $this->primaryDepartment = Department::factory()->forCollege($this->college)->create([
            'name' => 'Primary Department',
        ]);
        $this->secondaryDepartment = Department::factory()->forCollege($this->college)->create([
            'name' => 'Secondary Department',
        ]);

        EmployeeProfile::factory()->forDepartment($this->primaryDepartment)->create([
            'user_id' => $this->user->id,
            'department_id' => null,
        ]);
    });

    it('serves the college admin faculty index and show routes', function () {
        $profile = FacultyProfile::factory()->forDepartment($this->primaryDepartment)->create();

        $this->actingAs($this->user)
            ->get(route('college-faculty-profiles.index'))
            ->assertOk()
            ->assertSee('Faculty Profiles');

        $this->actingAs($this->user)
            ->get(route('college-faculty-profiles.show', $profile))
            ->assertOk()
            ->assertSee($profile->email);
    });

    it('creates a faculty profile and linked user inside the managed college', function () {
        Livewire::actingAs($this->user)
            ->test('pages::dept-admin.faculty-profiles.index')
            ->call('create')
            ->set('form.first_name', 'Lina')
            ->set('form.middle_name', 'M')
            ->set('form.last_name', 'Santos')
            ->set('form.email', 'lina.santos@example.test')
            ->set('form.campus_id', $this->campus->id)
            ->set('form.college_id', $this->college->id)
            ->set('form.department_id', $this->secondaryDepartment->id)
            ->set('form.academic_rank', 'Instructor I')
            ->call('save')
            ->assertHasNoErrors();

        $createdUser = User::query()->where('email', 'lina.santos@example.test')->first();

        expect($createdUser)->not->toBeNull()
            ->and($createdUser->hasRole('faculty'))->toBeTrue()
            ->and($createdUser->facultyProfile)->not->toBeNull()
            ->and($createdUser->facultyProfile->college_id)->toBe($this->college->id)
            ->and($createdUser->facultyProfile->department_id)->toBe($this->secondaryDepartment->id);
    });

    it('rejects faculty creation outside the managed college scope', function () {
        $outsideCampus = Campus::factory()->create();
        $outsideCollege = College::factory()->forCampus($outsideCampus)->create();
        $outsideDepartment = Department::factory()->forCollege($outsideCollege)->create();

        Livewire::actingAs($this->user)
            ->test('pages::dept-admin.faculty-profiles.index')
            ->call('create')
            ->set('form.first_name', 'Kris')
            ->set('form.last_name', 'Tan')
            ->set('form.email', 'kris.tan@example.test')
            ->set('form.campus_id', $outsideCampus->id)
            ->set('form.college_id', $outsideCollege->id)
            ->set('form.department_id', $outsideDepartment->id)
            ->call('save')
            ->assertHasErrors([
                'form.campus_id',
                'form.college_id',
                'form.department_id',
            ]);

        expect(User::query()->where('email', 'kris.tan@example.test')->exists())->toBeFalse();
    });

    it('blocks access to faculty profiles outside the managed college', function () {
        $outsideCampus = Campus::factory()->create();
        $outsideCollege = College::factory()->forCampus($outsideCampus)->create();
        $outsideDepartment = Department::factory()->forCollege($outsideCollege)->create();
        $outsideProfile = FacultyProfile::factory()->forDepartment($outsideDepartment)->create();

        $this->withoutExceptionHandling();

        expect(fn () => $this->actingAs($this->user)
            ->get(route('college-faculty-profiles.show', $outsideProfile)))
            ->toThrow(ModelNotFoundException::class);
    });
});
