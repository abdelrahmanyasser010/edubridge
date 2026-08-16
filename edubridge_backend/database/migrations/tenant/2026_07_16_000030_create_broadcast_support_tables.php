<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('broadcast_messages', function (Blueprint $table) {
            $table->id();
            $table->string('target', 32);
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedBigInteger('created_by_central_user_id')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('opened_by_central_user_id')->index();
            $table->string('subject', 180);
            $table->string('status', 32)->default('open')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->unsignedBigInteger('author_central_user_id')->index();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('support_ticket_replies');
        Schema::connection('tenant')->dropIfExists('support_tickets');
        Schema::connection('tenant')->dropIfExists('broadcast_messages');
    }
};
