<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('teacher_section_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->unsignedSmallInteger('weekly_quota')->default(1);
            $table->boolean('is_homeroom')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['academic_term_id', 'teacher_id', 'section_id', 'subject_id'], 'tss_term_teacher_section_subject_unique');
            $table->index(['academic_term_id', 'section_id']);
            $table->index(['teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('teacher_section_subject');
    }
};
