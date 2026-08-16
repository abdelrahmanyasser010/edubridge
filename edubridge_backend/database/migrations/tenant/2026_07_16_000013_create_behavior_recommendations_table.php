<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('behavior_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('behavior_note_id')->constrained('behavior_notes')->cascadeOnDelete();
            $table->unsignedBigInteger('created_by_central_user_id')->index();
            $table->text('body');
            $table->string('status', 32)->default('published')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('behavior_recommendations');
    }
};
