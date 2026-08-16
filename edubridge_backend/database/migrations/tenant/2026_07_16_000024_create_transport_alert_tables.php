<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('bus_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('bus_route_id')->constrained('bus_routes')->cascadeOnDelete();
            $table->foreignId('parent_id')->constrained('parents')->restrictOnDelete();
            $table->date('service_date');
            $table->string('direction', 16);
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'service_date', 'direction']);
        });

        Schema::connection('tenant')->create('transport_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_route_id')->constrained('bus_routes')->cascadeOnDelete();
            $table->foreignId('bus_trip_id')->nullable()->constrained('bus_trips')->nullOnDelete();
            $table->string('type', 32);
            $table->string('message', 255);
            $table->unsignedBigInteger('created_by_central_user_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('transport_alerts');
        Schema::connection('tenant')->dropIfExists('bus_opt_outs');
    }
};
