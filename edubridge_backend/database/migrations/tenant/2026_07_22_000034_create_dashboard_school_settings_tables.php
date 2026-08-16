<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::connection('tenant')->create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();
            $table->string('provider', 128)->nullable();
            $table->string('status', 32)->default('not_configured')->index();
            $table->json('config')->nullable();
            $table->string('secret_ref')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 32)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('integration_settings');
        Schema::connection('tenant')->dropIfExists('school_settings');
    }
};
