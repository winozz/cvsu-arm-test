<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('faculty_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('employee_no')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnUpdate();
            $table->foreignId('college_id')->constrained('colleges')->cascadeOnUpdate();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnUpdate();
            $table->string('academic_rank')->nullable();
            $table->string('email')->unique();
            $table->string('contactno')->nullable();
            $table->text('address')->nullable();
            $table->string('sex')->nullable();
            $table->date('birthday')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_profiles');
    }
};
