<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('bus_routes', function (Blueprint $table) {
            $table->string('plate_number', 32)->nullable()->after('driver_name');
            $table->string('driver_phone', 32)->nullable()->after('plate_number');
            $table->string('supervisor_name', 120)->nullable()->after('driver_phone');
            $table->time('estimated_arrival_time')->nullable()->after('supervisor_name');
        });

        Schema::connection('tenant')->table('transport_alerts', function (Blueprint $table) {
            $table->unsignedSmallInteger('delay_minutes')->nullable()->after('message');
            $table->json('channels')->nullable()->after('delay_minutes');
        });

        Schema::connection('tenant')->create('transport_contact_driver_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_route_id')->constrained('bus_routes')->cascadeOnDelete();
            $table->string('driver_phone', 32)->nullable();
            $table->string('outcome', 32);
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('created_by_central_user_id')->index();
            $table->timestamps();

            $table->index(['bus_route_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('transport_contact_driver_logs');

        Schema::connection('tenant')->table('transport_alerts', function (Blueprint $table) {
            $table->dropColumn(['delay_minutes', 'channels']);
        });

        Schema::connection('tenant')->table('bus_routes', function (Blueprint $table) {
            $table->dropColumn(['plate_number', 'driver_phone', 'supervisor_name', 'estimated_arrival_time']);
        });
    }
};
