<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('teacher_substitutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teaching_session_id')->constrained('teaching_sessions')->cascadeOnDelete();
            $table->foreignId('original_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->foreignId('substitute_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->unsignedBigInteger('assigned_by_central_user_id')->index();
            $table->string('reason', 255)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['teaching_session_id', 'status']);
            $table->index(['substitute_teacher_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('teacher_substitutions');
    }
};
