<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('title', 200);
            $table->text('body');
            $table->string('type', 32)->default('announcement');
            $table->string('default_target_type', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_central_user_id')->nullable();
            $table->foreignId('updated_by_central_user_id')->nullable();
            $table->timestamps();

            $table->unique('name');
            $table->index(['is_active', 'type']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('message_templates');
    }
};
