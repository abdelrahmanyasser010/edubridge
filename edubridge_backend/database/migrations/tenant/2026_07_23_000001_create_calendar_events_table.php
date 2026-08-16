<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('calendar_events', function (Blueprint $table) {
            $table->id();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('type', 32)->default('event')->index();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable()->index();
            $table->boolean('all_day')->default(false);
            $table->string('audience_type', 32)->default('all');
            $table->json('audience_ids')->nullable();
            $table->string('location', 180)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->unsignedBigInteger('created_by_central_user_id')->index();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('calendar_events');
    }
};
