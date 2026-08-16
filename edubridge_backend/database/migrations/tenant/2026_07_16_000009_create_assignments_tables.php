<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_id')->constrained('teacher_section_subject')->restrictOnDelete();
            $table->foreignId('assigned_by_teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->index(['allocation_id', 'status']);
            $table->index(['assigned_by_teacher_id', 'status']);
            $table->index(['due_at']);
        });

        Schema::connection('tenant')->create('assignment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['assignment_id', 'file_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('assignment_attachments');
        Schema::connection('tenant')->dropIfExists('assignments');
    }
};
