<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Subject;
use Livewire\Form;

class SubjectForm extends Form
{
    public ?Subject $subject = null;

    public string $code = '';

    public string $title = '';

    public string $description = '';

    public ?int $lecture_units = null;

    public ?int $laboratory_units = null;

    public bool $is_credit = true;

    public bool $is_active = true;

    public function setSubject(Subject $subject): void
    {
        $this->subject = $subject;
        $this->code = $subject->code;
        $this->title = $subject->title;
        $this->description = $subject->description ?? '';
        $this->lecture_units = $subject->lecture_units;
        $this->laboratory_units = $subject->laboratory_units;
        $this->is_credit = $subject->is_credit;
        $this->is_active = $subject->is_active;
    }

    public function resetForm(): void
    {
        $this->reset(['subject', 'code', 'title', 'description', 'lecture_units', 'laboratory_units']);
        $this->is_credit = true;
        $this->is_active = true;
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'lecture_units' => ['required', 'integer', 'min:0', 'max:99'],
            'laboratory_units' => ['required', 'integer', 'min:0', 'max:99'],
            'is_credit' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function payload(array $validated): array
    {
        return [
            'code' => trim($validated['code']),
            'title' => trim($validated['title']),
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'lecture_units' => (int) $validated['lecture_units'],
            'laboratory_units' => (int) $validated['laboratory_units'],
            'is_credit' => (bool) $validated['is_credit'],
            'is_active' => (bool) $validated['is_active'],
        ];
    }
}
