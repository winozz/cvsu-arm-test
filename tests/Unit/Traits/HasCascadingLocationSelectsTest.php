<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Traits\HasCascadingLocationSelects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('HasCascadingLocationSelects', function () {
    beforeEach(function () {
        $this->host = new class
        {
            use HasCascadingLocationSelects;

            public array $colleges = [];

            public array $departments = [];

            public object $form;

            public function __construct()
            {
                $this->form = (object) [
                    'campus_id' => null,
                    'college_id' => null,
                    'department_id' => null,
                ];
            }
        };
    });

    it('loads colleges from array-backed campus select payloads and clears downstream values', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create(['name' => 'College of Computing']);
        $otherCampus = Campus::factory()->create();
        $otherCollege = College::factory()->forCampus($otherCampus)->create(['name' => 'Other College']);

        $this->host->form->college_id = $otherCollege->id;
        $this->host->form->department_id = 99;

        $this->host->updatedFormCampusId(['value' => (string) $campus->id]);

        expect($this->host->form->campus_id)->toBe($campus->id)
            ->and($this->host->form->college_id)->toBeNull()
            ->and($this->host->form->department_id)->toBeNull()
            ->and($this->host->colleges)->toBe([
                ['label' => 'College of Computing', 'value' => $college->id],
            ])
            ->and($this->host->departments)->toBe([]);
    });

    it('loads departments from normalized college ids and clears invalid selections', function () {
        $campus = Campus::factory()->create();
        $college = College::factory()->forCampus($campus)->create();
        $department = Department::factory()->forCollege($college)->create(['name' => 'Computer Studies Department']);

        $this->host->form->department_id = 88;

        $this->host->updatedFormCollegeId((string) $college->id);

        expect($this->host->form->college_id)->toBe($college->id)
            ->and($this->host->form->department_id)->toBeNull()
            ->and($this->host->departments)->toBe([
                ['label' => 'Computer Studies Department', 'value' => $department->id],
            ]);

        $this->host->updatedFormCollegeId('invalid');

        expect($this->host->form->college_id)->toBeNull()
            ->and($this->host->form->department_id)->toBeNull()
            ->and($this->host->departments)->toBe([]);
    });
});
