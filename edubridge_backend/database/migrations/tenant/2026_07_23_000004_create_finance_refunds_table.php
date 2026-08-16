<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('finance_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_payment_id')->constrained('finance_payments')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 32)->default('completed')->index();
            $table->string('reason', 500);
            $table->string('reference', 128)->nullable()->unique();
            $table->unsignedBigInteger('created_by_central_user_id')->nullable()->index();
            $table->timestamps();

            $table->index(['finance_payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('finance_refunds');
    }
};
