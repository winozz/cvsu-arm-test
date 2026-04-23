<?php

use App\Models\Subject;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('status')->default(Subject::STATUS_DRAFT)->after('is_active');
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
        });

        DB::table('subjects')
            ->whereNull('deleted_at')
            ->update([
                'status' => Subject::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);

        Schema::create('subject_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('college_id')->nullable()->constrained('colleges')->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['subject_id', 'campus_id']);
            $table->index(['campus_id', 'college_id']);
        });

        Schema::create('subject_assignment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('request_type');
            $table->string('status')->default('pending');
            $table->foreignId('source_campus_id')->constrained('campuses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('source_college_id')->nullable()->constrained('colleges')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('target_campus_id')->constrained('campuses')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('target_college_id')->nullable()->constrained('colleges')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'target_campus_id', 'target_college_id'], 'subject_requests_target_scope_index');
            $table->index(['status', 'source_campus_id', 'source_college_id'], 'subject_requests_source_scope_index');
        });

        Schema::create('subject_user_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('action');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['subject_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_user_actions');
        Schema::dropIfExists('subject_assignment_requests');
        Schema::dropIfExists('subject_assignments');

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['status', 'submitted_at']);
        });
    }
};
