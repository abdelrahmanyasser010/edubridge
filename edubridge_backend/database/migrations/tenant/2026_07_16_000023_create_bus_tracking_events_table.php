<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('bus_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_trip_id')->constrained('bus_trips')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('speed_kph')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['bus_trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('bus_tracking_events');
    }
};
