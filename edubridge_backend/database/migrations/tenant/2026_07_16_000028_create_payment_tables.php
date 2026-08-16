<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('title', 180);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 32)->default('open')->index();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_id')->constrained('fees')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('provider_session_id', 128)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 32)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payment_sessions');
        Schema::connection('tenant')->dropIfExists('fees');
    }
};
