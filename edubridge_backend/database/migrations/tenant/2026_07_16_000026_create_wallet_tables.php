<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->unique()->constrained('students')->cascadeOnDelete();
            $table->string('currency', 3);
            $table->decimal('cached_balance', 12, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('reference_type', 64);
            $table->string('reference_id', 128);
            $table->unsignedBigInteger('actor_central_user_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['reference_type', 'reference_id']);
            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('wallet_transactions');
        Schema::connection('tenant')->dropIfExists('wallets');
    }
};
