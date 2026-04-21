<?php

namespace App\Livewire\Forms\Admin;

use App\Models\Room;
use Illuminate\Validation\Rule;
use Livewire\Form;
use LogicException;

class RoomForm extends Form
{
    public ?Room $room = null;

    public ?int $campus_id = null;

    public ?int $college_id = null;

    public ?int $department_id = null;

    public string $name = '';

    public string $floor_no = '';

    public ?int $room_no = null;

    public string $type = 'LECTURE';

    public string $description = '';

    public string $location = '';

    public bool $is_active = true;

    public string $status = 'USEABLE';

    public function setContext(int $campusId, int $collegeId, int $departmentId): void
    {
        $this->campus_id = $campusId;
        $this->college_id = $collegeId;
        $this->department_id = $departmentId;
    }

    public function setRoom(Room $room): void
    {
        $this->room = $room;
        $this->campus_id = $room->campus_id;
        $this->college_id = $room->college_id;
        $this->department_id = $room->department_id;
        $this->name = $room->name;
        $this->floor_no = (string) $room->floor_no;
        $this->room_no = $room->room_no;
        $this->type = $room->type;
        $this->description = $room->description ?? '';
        $this->location = $room->location ?? '';
        $this->is_active = $room->is_active;
        $this->status = $room->status ?? 'USEABLE';
    }

    public function resetForm(?int $campusId = null, ?int $collegeId = null, ?int $departmentId = null): void
    {
        $this->reset(['room', 'name', 'floor_no', 'room_no', 'description', 'location']);
        $this->campus_id = $campusId;
        $this->college_id = $collegeId;
        $this->department_id = $departmentId;
        $this->type = 'LECTURE';
        $this->status = 'USEABLE';
        $this->is_active = true;
    }

    public function validateForm(): array
    {
        return $this->validate($this->rules());
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'exists:campuses,id'],
            'college_id' => ['required', 'exists:colleges,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'floor_no' => ['required', 'string', 'max:255'],
            'room_no' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('rooms', 'room_no')
                    ->ignore($this->room?->id)
                    ->where(fn ($query) => $query
                        ->where('department_id', $this->department_id)
                        ->whereNull('deleted_at')),
            ],
            'type' => ['required', Rule::in(array_keys(Room::TYPES))],
            'description' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'status' => ['nullable', Rule::in(array_keys(Room::STATUSES))],
        ];
    }

    public function payload(array $validated): array
    {
        return [
            'campus_id' => (int) $validated['campus_id'],
            'college_id' => (int) $validated['college_id'],
            'department_id' => (int) $validated['department_id'],
            'name' => trim($validated['name']),
            'floor_no' => trim($validated['floor_no']),
            'room_no' => (int) $validated['room_no'],
            'type' => $validated['type'],
            'description' => filled($validated['description']) ? trim($validated['description']) : null,
            'location' => filled($validated['location']) ? trim($validated['location']) : null,
            'is_active' => (bool) $validated['is_active'],
            'status' => filled($validated['status']) ? $validated['status'] : null,
        ];
    }

    public function assertContext(): void
    {
        if (! $this->campus_id || ! $this->college_id || ! $this->department_id) {
            throw new LogicException('Cannot manage rooms without department context.');
        }
    }

    public function assertRoomMatchesContext(Room $room): void
    {
        $this->assertContext();

        if ((int) $room->campus_id !== (int) $this->campus_id
            || (int) $room->college_id !== (int) $this->college_id
            || (int) $room->department_id !== (int) $this->department_id) {
            throw new LogicException('Cannot manage a room outside the current department context.');
        }
    }
}
