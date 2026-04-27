<?php

namespace App\Models;

use Database\Factories\RoomCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'is_active'])]
class RoomCategory extends Model
{
    /** @use HasFactory<RoomCategoryFactory> */
    use HasFactory, SoftDeletes;

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value) => (bool) $value,
        );
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
