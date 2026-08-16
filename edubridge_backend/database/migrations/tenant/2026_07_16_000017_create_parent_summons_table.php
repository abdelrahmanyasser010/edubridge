<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('parent_summons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->restrictOnDelete();
            $table->unsignedBigInteger('created_by_central_user_id')->index();
            $table->timestamp('scheduled_at');
            $table->string('reason', 255);
            $table->string('status', 32)->default('pending')->index();
            $table->string('response', 32)->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'status']);
            $table->index(['student_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('parent_summons');
    }
};
