<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->restrictOnDelete();
            $table->foreignId('allocation_id')->constrained('teacher_section_subject')->restrictOnDelete();
            $table->string('title', 180);
            $table->string('type', 32);
            $table->decimal('max_score', 8, 2);
            $table->decimal('weight', 5, 2)->default(1);
            $table->string('status', 32)->default('draft')->index();
            $table->timestamps();

            $table->index(['allocation_id', 'status']);
        });

        Schema::connection('tenant')->create('grade_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('entered_by_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['assessment_id', 'student_id']);
            $table->index(['student_id', 'assessment_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('grade_entries');
        Schema::connection('tenant')->dropIfExists('assessments');
    }
};
