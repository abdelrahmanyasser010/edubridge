<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('leave_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('requested_leave_at');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedBigInteger('reviewed_by_central_user_id')->nullable()->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->char('gate_token_hash', 64)->nullable()->unique();
            $table->timestamp('gate_token_expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('leave_permits');
    }
};
