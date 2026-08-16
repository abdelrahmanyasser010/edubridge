<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('attendance_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_session_id')->constrained('teaching_sessions')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->json('records');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['teaching_session_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('attendance_drafts');
    }
};
