<?php

namespace App\Models;

use Database\Factories\FacultyProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacultyProfile extends Model
{
    /** @use HasFactory<FacultyProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'faculty_profiles';

    protected $fillable = [
        'user_id',
        'employee_no',
        'first_name',
        'middle_name',
        'last_name',
        'campus_id',
        'college_id',
        'department_id',
        'academic_rank',
        'email',
        'contactno',
        'address',
        'sex',
        'birthday',
        'updated_by',
    ];

    /**
     * Get the user that owns the FacultyProfile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
