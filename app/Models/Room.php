<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['campus_id', 'college_id', 'department_id', 'name', 'floor_no', 'room_no', 'type', 'description', 'location', 'is_active', 'status'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'LECTURE' => 'Lecture',
        'LABORATORY' => 'Laboratory',
    ];

    public const STATUSES = [
        'USEABLE' => 'Usable',
        'NOT_USEABLE' => 'Not Usable',
        'UNDER_RENOVATION' => 'Under Renovation',
        'UNDER_CONSTRUCTION' => 'Under Construction',
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
            get: fn () => self::TYPES[$this->type] ?? ($this->type ?: '-'),
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => self::STATUSES[$this->status] ?? ($this->status ?: '-'),
        );
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
}
