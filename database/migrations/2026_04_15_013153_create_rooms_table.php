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
            $table->foreignId('campus_id')->onUpdate('cascade');
            $table->foreignId('college_id')->onUpdate('cascade');
            $table->foreignId('department_id')->onUpdate('cascade');

            $table->string('name');
            $table->string('floor_no');
            $table->bigInteger('room_no');
            $table->enum('type', ['LECTURE', 'LABORATORY']);
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
