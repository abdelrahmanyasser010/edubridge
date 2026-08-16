<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('wallet_payment_tokens', function (Blueprint $table) {
            $table->string('scope', 32)->default('canteen')->after('max_amount');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('wallet_payment_tokens', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
