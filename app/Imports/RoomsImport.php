<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Room;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RoomsImport implements ToCollection, WithHeadingRow
{
    public function __construct(
        protected Department $department
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $roomNo = $this->normalizeRoomNumber($row['room_no'] ?? null);

            if ($roomNo === null) {
                continue;
            }

            $type = $this->normalizeEnumValue((string) ($row['type'] ?? ''), Room::TYPES);
            $status = $this->normalizeEnumValue((string) ($row['status'] ?? ''), Room::STATUSES);
            $isActive = $this->normalizeBoolean($row['is_active'] ?? true);

            $room = Room::withTrashed()->updateOrCreate(
                [
                    'department_id' => $this->department->id,
                    'room_no' => $roomNo,
                ],
                [
                    'campus_id' => $this->department->campus_id,
                    'college_id' => $this->department->college_id,
                    'name' => filled($row['name'] ?? null) ? trim((string) $row['name']) : 'Room '.$roomNo,
                    'floor_no' => filled($row['floor_no'] ?? null) ? trim((string) $row['floor_no']) : '1',
                    'type' => $type ?? 'LECTURE',
                    'description' => filled($row['description'] ?? null) ? trim((string) $row['description']) : null,
                    'location' => filled($row['location'] ?? null) ? trim((string) $row['location']) : null,
                    'is_active' => $isActive,
                    'status' => $status,
                ]
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

    protected function normalizeEnumValue(string $value, array $options): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = strtoupper(str_replace([' ', '-'], '_', trim($value)));

        return array_key_exists($normalized, $options) ? $normalized : null;
    }

    protected function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return ! in_array($normalized, ['0', 'false', 'inactive', 'no'], true);
    }
}
