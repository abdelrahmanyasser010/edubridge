<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('event_id', 128);
            $table->json('payload');
            $table->string('status', 32)->default('received')->index();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payment_webhook_events');
    }
};
