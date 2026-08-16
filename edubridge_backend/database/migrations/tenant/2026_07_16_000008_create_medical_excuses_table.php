<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('medical_excuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->restrictOnDelete();
            $table->foreignId('file_id')->constrained('files')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by_central_user_id')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status', 'starts_on']);
            $table->index(['parent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('medical_excuses');
    }
};
