<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 128)->index();
            $table->string('title', 180);
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->unsignedBigInteger('actor_central_user_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->unsignedBigInteger('central_user_id')->index();
            $table->string('channel', 32);
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['notification_id', 'central_user_id', 'channel'],
                'notif_delivery_user_channel_unique'
            );
            $table->index(['central_user_id', 'read_at']);
        });

        Schema::connection('tenant')->create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('central_user_id')->index();
            $table->string('type', 128);
            $table->string('channel', 32);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['central_user_id', 'type', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('notification_preferences');
        Schema::connection('tenant')->dropIfExists('notification_deliveries');
        Schema::connection('tenant')->dropIfExists('notifications');
    }
};
