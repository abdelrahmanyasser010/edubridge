<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->restrictOnDelete();
            $table->foreignId('allocation_id')->constrained('teacher_section_subject')->restrictOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 64)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->unique(['allocation_id', 'weekday', 'starts_at'], 'slot_allocation_weekday_start_unique');
            $table->index(['academic_term_id', 'weekday']);
        });

        Schema::connection('tenant')->create('teaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_slot_id')->constrained('schedule_slots')->cascadeOnDelete();
            $table->foreignId('allocation_id')->constrained('teacher_section_subject')->restrictOnDelete();
            $table->date('session_date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('status', 32)->default('scheduled')->index();
            $table->timestamps();

            $table->unique(['schedule_slot_id', 'session_date']);
            $table->index(['allocation_id', 'session_date']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('teaching_sessions');
        Schema::connection('tenant')->dropIfExists('schedule_slots');
    }
};
