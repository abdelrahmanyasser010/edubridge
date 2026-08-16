<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('bus_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 64)->unique();
            $table->unsignedInteger('capacity');
            $table->string('driver_name', 120)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('bus_route_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_route_id')->constrained('bus_routes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['bus_route_id', 'status']);
            $table->index(['student_id', 'status']);
        });

        Schema::connection('tenant')->create('bus_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_route_id')->constrained('bus_routes')->cascadeOnDelete();
            $table->date('service_date');
            $table->string('direction', 16);
            $table->string('status', 32)->default('scheduled')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['bus_route_id', 'service_date', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('bus_trips');
        Schema::connection('tenant')->dropIfExists('bus_route_assignments');
        Schema::connection('tenant')->dropIfExists('bus_routes');
    }
};
