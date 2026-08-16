<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('dashboard_canvas_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 120)->nullable();
            $table->json('payload');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by_central_user_id');
            $table->timestamps();

            $table->index(['key', 'version']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('dashboard_canvas_configs');
    }
};
