<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_thread_id')->constrained('conversation_threads')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_central_user_id')->index();
            $table->text('body')->nullable();
            $table->timestamps();

            $table->index(['conversation_thread_id', 'id']);
        });

        Schema::connection('tenant')->create('conversation_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained('conversation_messages')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['conversation_message_id', 'file_id'], 'conversation_message_file_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('conversation_message_attachments');
        Schema::connection('tenant')->dropIfExists('conversation_messages');
    }
};
