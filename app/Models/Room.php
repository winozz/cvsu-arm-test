<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['campus_id', 'college_id', 'department_id', 'name', 'floor_no', 'room_no', 'type', 'description', 'location', 'is_active', 'status'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;
}
