<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('behavior_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained('teacher_section_subject')->restrictOnDelete();
            $table->foreignId('created_by_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->string('severity', 32)->default('info');
            $table->string('status', 32)->default('pending_review')->index();
            $table->unsignedBigInteger('reviewed_by_central_user_id')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['allocation_id', 'status']);
        });

        Schema::connection('tenant')->create('behavior_note_timeline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('behavior_note_id')->constrained('behavior_notes')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->unsignedBigInteger('actor_central_user_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('behavior_note_timeline');
        Schema::connection('tenant')->dropIfExists('behavior_notes');
    }
};
