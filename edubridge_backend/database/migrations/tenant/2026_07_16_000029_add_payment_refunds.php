<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_session_id')->constrained('payment_sessions')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 32)->default('completed')->index();
            $table->string('reference_id', 128)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('payment_refunds');
    }
};
