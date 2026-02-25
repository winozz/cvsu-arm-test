<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'user_id',
        'first_name',
        'middle_name',
        'last_name',
        'position',
        'branch_id',
        'department_id',
    ];

    /**
     * Get the user that owns the Employee profile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
