<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Campus;
use Illuminate\Validation\Rule;
use Livewire\Form;
use LogicException;

class CampusForm extends Form
{
    public ?Campus $campus = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public function setCampus(Campus $campus): void
    {
        $this->campus = $campus;
        $this->code = $campus->code;
        $this->name = $campus->name;
        $this->description = $campus->description ?? '';
        $this->is_active = $campus->is_active;
    }

    public function update(): Campus
    {
        if (! $this->campus) {
            throw new LogicException('Cannot update a campus without an active record.');
        }

        $validated = $this->validateForm();

        $this->campus->update($this->payload($validated));

        $campus = $this->campus->fresh();

        $this->resetForm();

        return $campus;
    }

    public function resetForm(): void
    {
        $this->reset(['campus', 'code', 'name', 'description']);
        $this->is_active = true;
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    protected function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:255',
                Rule::unique('campuses', 'code')
                    ->ignore($this->campus?->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function payload(array $validated): array
    {
        return [
            'code' => trim($validated['code']),
            'name' => trim($validated['name']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
