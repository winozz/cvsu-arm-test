<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Program;
use App\Traits\CanManage;
use Illuminate\Validation\Rule;
use Livewire\Form;
use LogicException;

class ProgramForm extends Form
{
    use CanManage;

    public ?Program $program = null;

    public string $code = '';

    public string $title = '';

    public string $description = '';

    public ?int $no_of_years = null;

    public string $level = '';

    public bool $is_active = true;

    public function setProgram(Program $program): void
    {
        $this->program = $program;
        $this->code = $program->code;
        $this->title = $program->title;
        $this->description = $program->description ?? '';
        $this->no_of_years = $program->no_of_years;
        $this->level = $program->level;
        $this->is_active = $program->is_active;
    }

    public function store(): Program
    {
        $this->ensureCanManage('programs.create');

        $validated = $this->validateForm();

        $program = Program::create($this->payload($validated));

        $this->resetForm();

        return $program;
    }

    public function update(): Program
    {
        $this->ensureCanManage('programs.update');

        if (! $this->program) {
            throw new LogicException('Cannot update a program without an active record.');
        }

        $validated = $this->validateForm();

        $this->program->update($this->payload($validated));

        $program = $this->program->fresh();

        $this->resetForm();

        return $program;
    }

    public function resetForm(): void
    {
        $this->reset(['program', 'code', 'title', 'description', 'no_of_years', 'level']);
        $this->is_active = true;
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'no_of_years' => ['required', 'integer', 'min:1', 'max:10'],
            'level' => ['required', 'string', Rule::in(array_keys(Program::LEVELS))],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function payload(array $validated): array
    {
        return [
            'code' => trim($validated['code']),
            'title' => trim($validated['title']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'no_of_years' => (int) $validated['no_of_years'],
            'level' => $validated['level'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
