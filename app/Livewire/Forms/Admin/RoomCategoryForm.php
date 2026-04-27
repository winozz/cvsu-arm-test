<?php

namespace App\Livewire\Forms\Admin;

use App\Models\RoomCategory;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

class RoomCategoryForm extends Form
{
    public ?RoomCategory $roomCategory = null;

    public string $name = '';

    public string $slug = '';

    public bool $is_active = true;

    public function setRoomCategory(RoomCategory $roomCategory): void
    {
        $this->roomCategory = $roomCategory;
        $this->name = $roomCategory->name;
        $this->slug = $roomCategory->slug;
        $this->is_active = $roomCategory->is_active;
    }

    public function resetForm(): void
    {
        $this->reset(['roomCategory', 'name', 'slug']);
        $this->is_active = true;
    }

    public function validateForm(): array
    {
        $validated = $this->validate($this->rules());
        $validated['slug'] = Str::slug($validated['slug']);

        return $validated;
    }

    public function rules(): array
    {
        $roomCategoryId = $this->roomCategory?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('room_categories', 'name')->ignore($roomCategoryId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('room_categories', 'slug')->ignore($roomCategoryId),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
