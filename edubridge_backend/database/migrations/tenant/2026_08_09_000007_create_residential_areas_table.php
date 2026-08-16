<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('residential_areas', function (Blueprint $table) {
            $table->id();
            $table->string('city', 120);
            $table->string('name', 160);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['city', 'name']);
            $table->index(['status', 'city']);
        });

        Schema::connection('tenant')->table('students', function (Blueprint $table) {
            $table->foreignId('residential_area_id')->nullable()->after('section_id')->constrained('residential_areas')->nullOnDelete();
        });

        Schema::connection('tenant')->table('parents', function (Blueprint $table) {
            $table->foreignId('residential_area_id')->nullable()->after('national_id_last4')->constrained('residential_areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('residential_area_id');
        });

        Schema::connection('tenant')->table('parents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('residential_area_id');
        });

        Schema::connection('tenant')->dropIfExists('residential_areas');
    }
};
