<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('grade_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_entry_id')->constrained('grade_entries')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 32)->default('open')->index();
            $table->unsignedBigInteger('reviewed_by_central_user_id')->nullable()->index();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['grade_entry_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('grade_appeals');
    }
};
