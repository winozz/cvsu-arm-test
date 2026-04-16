<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'title', 'description', 'lecture_units', 'laboratory_units', 'is_credit', 'is_active'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, SoftDeletes;
}
