<?php

namespace Database\Factories\Concerns;

use App\Models\Department;

trait ResolvesAcademicContext
{
    protected function resolveDepartment(): Department
    {
        return Department::query()->inRandomOrder()->first()
            ?? Department::factory()->create();
    }

    /**
     * @return array{campus_id: int, college_id: int, department_id: int}
     */
    protected function academicContext(?Department $department = null): array
    {
        $department ??= $this->resolveDepartment();

        return [
            'campus_id' => $department->campus_id,
            'college_id' => $department->college_id,
            'department_id' => $department->id,
        ];
    }
}
