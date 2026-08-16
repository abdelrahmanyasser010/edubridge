<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('conversation_threads', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 180)->nullable();
            $table->unsignedBigInteger('created_by_central_user_id')->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_thread_id')->constrained('conversation_threads')->cascadeOnDelete();
            $table->unsignedBigInteger('central_user_id')->index();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_thread_id', 'central_user_id'], 'conversation_participants_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('conversation_participants');
        Schema::connection('tenant')->dropIfExists('conversation_threads');
    }
};
