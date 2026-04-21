<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\EmployeeProfile;
use App\Models\FacultyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Campus model', function () {
    beforeEach(function () {
        $this->campus = Campus::factory()->create(['is_active' => 1]);
    });

    it('defines the expected fillable attributes', function () {
        expect((new Campus())->getFillable())->toBe(['name', 'code', 'description', 'is_active']);
    });

    it('casts is_active to boolean and exposes its colleges and departments', function () {
        $college = College::factory()->forCampus($this->campus)->create();
        $department = Department::factory()->forCollege($college)->create();

        expect($this->campus->fresh()->is_active)->toBeTrue()
            ->and($this->campus->colleges->modelKeys())->toContain($college->id)
            ->and($this->campus->departments->modelKeys())->toContain($department->id);
    });
});

describe('College model', function () {
    beforeEach(function () {
        $this->campus = Campus::factory()->create();
        $this->college = College::factory()->forCampus($this->campus)->create(['is_active' => 0]);
    });

    it('defines the expected fillable attributes', function () {
        expect((new College())->getFillable())->toBe(['name', 'code', 'description', 'campus_id', 'is_active']);
    });

    it('casts is_active to boolean and exposes its campus and departments', function () {
        $department = Department::factory()->forCollege($this->college)->create();

        expect($this->college->fresh()->is_active)->toBeFalse()
            ->and($this->college->campus->is($this->campus))->toBeTrue()
            ->and($this->college->departments->modelKeys())->toContain($department->id);
    });
});

describe('Department model', function () {
    beforeEach(function () {
        $this->college = College::factory()->create();
        $this->department = Department::factory()->forCollege($this->college)->create(['is_active' => 1]);
    });

    it('defines the expected fillable attributes', function () {
        expect((new Department())->getFillable())->toBe(['name', 'code', 'description', 'campus_id', 'college_id', 'is_active']);
    });

    it('casts is_active to boolean and exposes its campus and college', function () {
        expect($this->department->fresh()->is_active)->toBeTrue()
            ->and($this->department->campus->is($this->college->campus))->toBeTrue()
            ->and($this->department->college->is($this->college))->toBeTrue();
    });
});

describe('EmployeeProfile model', function () {
    beforeEach(function () {
        $this->profile = EmployeeProfile::factory()->create();
    });

    it('defines the expected fillable attributes', function () {
        expect((new EmployeeProfile())->getFillable())->toBe([
            'user_id',
            'employee_no',
            'first_name',
            'middle_name',
            'last_name',
            'position',
            'campus_id',
            'college_id',
            'department_id',
        ]);
    });

    it('belongs to the expected academic context models', function () {
        expect($this->profile->user)->not->toBeNull()
            ->and($this->profile->campus)->not->toBeNull()
            ->and($this->profile->college)->not->toBeNull()
            ->and($this->profile->department)->not->toBeNull();
    });
});

describe('FacultyProfile model', function () {
    beforeEach(function () {
        $this->profile = FacultyProfile::factory()->create();
    });

    it('defines the expected fillable attributes', function () {
        expect((new FacultyProfile())->getFillable())->toBe([
            'user_id',
            'employee_no',
            'first_name',
            'middle_name',
            'last_name',
            'campus_id',
            'college_id',
            'department_id',
            'academic_rank',
            'email',
            'contactno',
            'address',
            'sex',
            'birthday',
            'updated_by',
        ]);
    });

    it('belongs to the expected academic context models', function () {
        expect($this->profile->user)->not->toBeNull()
            ->and($this->profile->campus)->not->toBeNull()
            ->and($this->profile->college)->not->toBeNull()
            ->and($this->profile->department)->not->toBeNull();
    });
});
