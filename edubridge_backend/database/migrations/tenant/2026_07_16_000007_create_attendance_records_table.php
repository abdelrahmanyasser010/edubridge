<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_session_id')->constrained('teaching_sessions')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('status', 32);
            $table->foreignId('recorded_by_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['teaching_session_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('attendance_records');
    }
};
