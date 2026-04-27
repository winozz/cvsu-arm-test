<?php

namespace App\Imports;

use App\Enums\RoomStatusEnum;
use App\Models\College;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RoomsImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        protected College|Department $owner,
        protected ?Department $department = null,
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $roomNo = $this->normalizeRoomNumber($row['room_no'] ?? null);
            $roomCategory = $this->resolveRoomCategory($row);
            $status = Room::toDatabaseStatusValue(
                $this->normalizeEnumValue((string) ($row['status'] ?? ''), Room::STATUSES)
                    ?? RoomStatusEnum::USEABLE->value
            );
            $isActive = $this->normalizeBoolean($row['is_active'] ?? true);
            $department = $this->assignedDepartment();
            $college = $this->assignedCollege();
            $roomName = filled($row['name'] ?? null)
                ? trim((string) $row['name'])
                : trim(($roomCategory?->name ?? 'Room').($roomNo !== null ? ' '.$roomNo : ''));

            $attributes = [
                'campus_id' => $department?->campus_id ?? $college->campus_id,
                'college_id' => $department?->college_id ?? $college->id,
                'department_id' => $department?->id,
                'name' => $roomName,
                'floor_no' => filled($row['floor_no'] ?? null) ? trim((string) $row['floor_no']) : null,
                'room_no' => $roomNo,
                'room_category_id' => $roomCategory?->id ?? RoomCategory::query()->where('slug', 'lecture')->value('id'),
                'description' => filled($row['description'] ?? null) ? trim((string) $row['description']) : null,
                'location' => filled($row['location'] ?? null) ? trim((string) $row['location']) : null,
                'is_active' => $isActive,
                'status' => $status,
            ];

            $identity = [
                'college_id' => $attributes['college_id'],
                'department_id' => $attributes['department_id'],
            ];

            if ($roomNo !== null) {
                $identity['room_no'] = $roomNo;
            } else {
                $identity['name'] = $roomName;
            }

            $room = Room::withTrashed()->updateOrCreate(
                $identity,
                $attributes
            );

            if ($room->trashed()) {
                $room->restore();
            }
        }
    }

    protected function normalizeRoomNumber(mixed $value): ?int
    {
        if (! filled($value) || ! is_numeric($value)) {
            return null;
        }

        return max(1, (int) $value);
    }

    protected function resolveRoomCategory(Collection $row): ?RoomCategory
    {
        $candidate = (string) ($row['room_category'] ?? $row['type'] ?? '');

        if (! filled($candidate)) {
            return RoomCategory::query()->where('slug', 'lecture')->first();
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', trim($candidate)));

        return RoomCategory::query()
            ->get()
            ->first(function (RoomCategory $roomCategory) use ($normalized): bool {
                $normalizedName = strtolower(str_replace([' ', '-'], '_', $roomCategory->name));
                $normalizedSlug = strtolower(str_replace([' ', '-'], '_', $roomCategory->slug));

                return $normalized === $normalizedName || $normalized === $normalizedSlug;
            })
            ?? RoomCategory::query()->where('slug', 'lecture')->first();
    }

    protected function normalizeEnumValue(string $value, array $options): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $candidate = trim($value);

        if (array_key_exists($candidate, $options)) {
            return $candidate;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', $candidate));

        foreach ($options as $key => $label) {
            $normalizedKey = strtolower(str_replace([' ', '-'], '_', (string) $key));
            $normalizedLabel = strtolower(str_replace([' ', '-'], '_', (string) $label));

            if ($normalized === $normalizedKey || $normalized === $normalizedLabel) {
                return (string) $key;
            }
        }

        return null;
    }

    protected function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return ! in_array($normalized, ['0', 'false', 'inactive', 'no'], true);
    }

    protected function assignedDepartment(): ?Department
    {
        if ($this->owner instanceof Department) {
            return $this->owner;
        }

        return $this->department;
    }

    protected function assignedCollege(): College
    {
        if ($this->owner instanceof College) {
            return $this->owner;
        }

        return $this->owner->college ?? College::query()->findOrFail($this->owner->college_id);
    }
}
