<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        $timestamp = now();
        $categories = collect([
            'Lecture',
            'Laboratory',
            'Lecture Laboratory',
            'Workshop',
            'Sports Facility',
            'Auditorium',
            'Office',
            'Conference Room',
        ])->map(fn (string $name): array => [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        DB::table('room_categories')->insert($categories);
    }

    public function down(): void
    {
        Schema::dropIfExists('room_categories');
    }
};
