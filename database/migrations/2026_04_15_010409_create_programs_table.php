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
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('title');
            $table->string('description')->nullable();
            $table->integer('no_of_years');
            $table->enum('level', ['UNDERGRADUATE', 'GRADUATE', 'PRE-BACCALAUREATE', 'POST-BACCALAUREATE']);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('college_programs', function (Blueprint $table) {
            $table->foreignId('college_id')->constrained('colleges')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['college_id', 'program_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('college_programs');
        Schema::dropIfExists('programs');
    }
};
