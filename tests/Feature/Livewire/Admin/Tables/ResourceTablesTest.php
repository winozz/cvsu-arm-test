<?php

use App\Livewire\Admin\Tables\CampusesTable;
use App\Livewire\Admin\Tables\CollegesTable;
use App\Livewire\Admin\Tables\DepartmentsTable;
use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use Livewire\Livewire;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

describe('CampusesTable', function () {
    beforeEach(function () {
        $this->user = actingUserWithPermissions(['colleges.view']);
    });

    it('configures outside filters and the campuses export name', function () {
        $component = Livewire::actingAs($this->user)->test(CampusesTable::class);
        $setUp = $component->instance()->setUp();

        expect(config('livewire-powergrid.filter'))->toBe('outside')
            ->and($setUp[0]->fileName)->toBe('campuses-list')
            ->and($setUp[0]->type)->toBe([Exportable::TYPE_XLS, Exportable::TYPE_CSV]);
    });

    it('filters the datasource by soft delete state', function () {
        $activeCampus = Campus::factory()->create();
        $trashedCampus = Campus::factory()->create();
        $trashedCampus->delete();

        $component = Livewire::actingAs($this->user)->test(CampusesTable::class);

        expect($component->instance()->datasource()->pluck('id')->all())
            ->toContain($activeCampus->id)
            ->not->toContain($trashedCampus->id);

        $component->set('softDeletes', 'withTrashed');

        expect($component->instance()->datasource()->pluck('id')->all())
            ->toContain($activeCampus->id, $trashedCampus->id);

        $component->set('softDeletes', 'onlyTrashed');

        expect($component->instance()->datasource()->pluck('id')->all())
            ->toContain($trashedCampus->id)
            ->not->toContain($activeCampus->id);
    });

    it('builds a campus view action for authorized users', function () {
        $campus = Campus::factory()->create();

        $button = Livewire::actingAs($this->user)
            ->test(CampusesTable::class)
            ->instance()
            ->actions($campus)[0];

        expect($button->action)->toBe('view-campus')
            ->and($button->slot)->toBe('View')
            ->and($button->tag)->toBe('a')
            ->and($button->attributes['href'])->toBe(route('admin.campuses.show', ['campus' => $campus->id]));
    });
});

describe('CollegesTable', function () {
    beforeEach(function () {
        $this->user = actingUserWithPermissions(['departments.view']);
        $this->campus = Campus::factory()->create();
    });

    it('configures the colleges export name and created at filter', function () {
        $component = Livewire::actingAs($this->user)->test(CollegesTable::class, ['campusId' => $this->campus->id]);
        $setUp = $component->instance()->setUp();
        $filters = $component->instance()->filters();

        expect($setUp[0]->fileName)->toBe('colleges-list')
            ->and($filters[0]->field)->toBe('created_at');
    });

    it('scopes the datasource to the mounted campus and soft delete state', function () {
        $collegeInCampus = College::factory()->forCampus($this->campus)->create();
        $otherCampus = Campus::factory()->create();
        $collegeOutsideCampus = College::factory()->forCampus($otherCampus)->create();
        $trashedCollege = College::factory()->forCampus($this->campus)->create();
        $trashedCollege->delete();

        $component = Livewire::actingAs($this->user)->test(CollegesTable::class, ['campusId' => $this->campus->id]);

        expect($component->instance()->datasource()->pluck('id')->all())
            ->toContain($collegeInCampus->id)
            ->not->toContain($collegeOutsideCampus->id, $trashedCollege->id);

        $component->set('softDeletes', 'withTrashed');

        expect($component->instance()->datasource()->pluck('id')->all())
            ->toContain($collegeInCampus->id, $trashedCollege->id)
            ->not->toContain($collegeOutsideCampus->id);
    });

    it('builds a college view action for authorized users', function () {
        $college = College::factory()->forCampus($this->campus)->create();

        $button = Livewire::actingAs($this->user)
            ->test(CollegesTable::class, ['campusId' => $this->campus->id])
            ->instance()
            ->actions($college)[0];

        expect($button->action)->toBe('view-college')
            ->and($button->attributes['href'])->toBe(route('admin.campuses.college.show', [
                'campus' => $this->campus->id,
                'college' => $college->id,
            ]));
    });
});

describe('DepartmentsTable', function () {
    beforeEach(function () {
        $this->user = actingUserWithPermissions([
            'departments.update',
            'departments.delete',
            'departments.restore',
        ]);
        $this->campus = Campus::factory()->create();
        $this->college = College::factory()->forCampus($this->campus)->create();
    });

    it('configures the departments export name and scopes the datasource to the current college', function () {
        $departmentInCollege = Department::factory()->forCollege($this->college)->create();
        $otherCollege = College::factory()->forCampus($this->campus)->create();
        $departmentOutsideCollege = Department::factory()->forCollege($otherCollege)->create();
        $trashedDepartment = Department::factory()->forCollege($this->college)->create();
        $trashedDepartment->delete();

        $component = Livewire::actingAs($this->user)->test(DepartmentsTable::class, ['collegeId' => $this->college->id]);
        $setUp = $component->instance()->setUp();

        expect($setUp[0]->fileName)->toBe('departments-list')
            ->and($component->instance()->datasource()->pluck('id')->all())
            ->toContain($departmentInCollege->id)
            ->not->toContain($departmentOutsideCollege->id, $trashedDepartment->id);

        $component->set('softDeletes', 'withTrashed');

        expect($component->instance()->datasource()->pluck('id')->all())
            ->toContain($departmentInCollege->id, $trashedDepartment->id)
            ->not->toContain($departmentOutsideCollege->id);
    });

    it('builds edit, delete, and restore actions with the expected events', function () {
        $department = Department::factory()->forCollege($this->college)->create();

        $actions = Livewire::actingAs($this->user)
            ->test(DepartmentsTable::class, ['collegeId' => $this->college->id])
            ->instance()
            ->actions($department);

        expect(collect($actions)->pluck('action')->all())->toBe(['edit', 'delete', 'restore'])
            ->and($actions[0]->attributes['wire:click'])->toContain('openEditDepartmentModal')
            ->and($actions[1]->attributes['wire:click'])->toContain('confirmDeleteDepartment')
            ->and($actions[2]->attributes['wire:click'])->toContain('confirmRestoreDepartment');
    });

    it('hides the correct action buttons based on trash state', function () {
        $activeDepartment = Department::factory()->forCollege($this->college)->create();
        $trashedDepartment = Department::factory()->forCollege($this->college)->create();
        $trashedDepartment->delete();

        $component = Livewire::actingAs($this->user)->test(DepartmentsTable::class, ['collegeId' => $this->college->id])->instance();

        $activeRules = collect($component->actionRules($activeDepartment))
            ->mapWithKeys(fn ($rule) => [$rule->forAction => ($rule->rule['when'])($activeDepartment)]);

        $trashedRules = collect($component->actionRules($trashedDepartment))
            ->mapWithKeys(fn ($rule) => [$rule->forAction => ($rule->rule['when'])($trashedDepartment)]);

        expect($activeRules->all())->toBe([
            'edit' => false,
            'delete' => false,
            'restore' => true,
        ])->and($trashedRules->all())->toBe([
            'edit' => true,
            'delete' => true,
            'restore' => false,
        ]);
    });
});
