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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnUpdate();
            $table->foreignId('college_id')->constrained('colleges')->cascadeOnUpdate();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->cascadeOnUpdate();

            $table->string('name');
            $table->string('floor_no')->nullable();
            $table->bigInteger('room_no')->nullable();
            $table->foreignId('room_category_id')->nullable()->constrained('room_categories')->nullOnDelete()->cascadeOnUpdate();
            $table->string('description')->nullable();

            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['USEABLE', 'NOT_USEABLE', 'UNDER_RENOVATION', 'UNDER_CONSTRUCTION'])->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
