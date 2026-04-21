<?php

use App\Livewire\Forms\Admin\FacultyProfileUpdateForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\FacultyProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('FacultyProfileUpdateForm', function () {
    it('rejects changes to campus college and department during validation', function () {
        $profile = FacultyProfile::factory()->create();
        $otherCampus = Campus::factory()->create();
        $otherCollege = College::factory()->forCampus($otherCampus)->create();
        $otherDepartment = Department::factory()->forCollege($otherCollege)->create();

        $component = new class extends Component
        {
            public function render()
            {
                return null;
            }
        };

        $form = new FacultyProfileUpdateForm($component, 'form');
        $form->setValues($profile);
        $form->campus_id = $otherCampus->id;
        $form->college_id = $otherCollege->id;
        $form->department_id = $otherDepartment->id;

        try {
            $form->validateForm();

            $this->fail('Expected the faculty profile update form to reject assignment changes.');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKeys([
                'form.campus_id',
                'form.college_id',
                'form.department_id',
            ]);
        }
    });
});
