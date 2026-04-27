<?php

namespace App\Models;

use App\Enums\RoomStatusEnum;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['campus_id', 'college_id', 'department_id', 'name', 'floor_no', 'room_no', 'room_category_id', 'description', 'location', 'is_active', 'status'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, SoftDeletes;

    public const STATUSES = [
        RoomStatusEnum::USEABLE->value => 'Useable',
        RoomStatusEnum::NOT_USEABLE->value => 'Not Useable',
        RoomStatusEnum::UNDER_CONSTRUCTION->value => 'Under Construction',
        RoomStatusEnum::UNDER_RENOVATION->value => 'Under Renovation',
    ];

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => (bool) $value,
        );
    }

    protected function typeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->roomCategory?->name ?? '-',
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $normalizedStatus = self::normalizeStatusValue($this->status);

                if ($normalizedStatus !== null) {
                    return self::STATUSES[$normalizedStatus] ?? Str::headline(str_replace('_', ' ', $normalizedStatus));
                }

                $rawStatus = trim((string) $this->status);

                return $rawStatus !== '' ? Str::headline(str_replace('_', ' ', strtolower($rawStatus))) : '-';
            },
        );
    }

    public static function normalizeStatusValue(mixed $status): ?string
    {
        if (! filled($status)) {
            return null;
        }

        if ($status instanceof RoomStatusEnum) {
            return $status->value;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', trim((string) $status)));

        return RoomStatusEnum::tryFrom($normalized)?->value;
    }

    public static function toDatabaseStatusValue(mixed $status): ?string
    {
        $normalizedStatus = self::normalizeStatusValue($status);

        return $normalizedStatus !== null ? strtoupper($normalizedStatus) : null;
    }

    protected function displayName(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $name = trim((string) $this->name);
                $roomNumber = filled($this->room_no) ? 'Room '.$this->room_no : null;

                if ($name === '') {
                    return $roomNumber ?? '-';
                }

                if ($roomNumber === null || Str::contains(Str::lower($name), Str::lower((string) $this->room_no))) {
                    return $name;
                }

                return $name.' ('.$roomNumber.')';
            },
        );
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function roomCategory(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class)->withTrashed();
    }
}
