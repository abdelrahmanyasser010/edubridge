<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('payment_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('fee_id')->nullable()->change();
            $table->foreignId('finance_invoice_id')->nullable()->after('fee_id')->constrained('finance_invoices')->restrictOnDelete();
            $table->foreignId('wallet_id')->nullable()->after('finance_invoice_id')->constrained('wallets')->restrictOnDelete();
            $table->string('purpose', 32)->default('legacy_fee')->after('provider_session_id')->index();
            $table->string('method', 32)->nullable()->after('purpose');
            $table->string('idempotency_key', 64)->nullable()->after('method')->unique();
            $table->unsignedBigInteger('amount_minor')->nullable()->after('amount');
            $table->string('provider_reference', 128)->nullable()->after('currency')->index();
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->timestamp('paid_at')->nullable()->after('expires_at');
            $table->timestamp('failed_at')->nullable()->after('paid_at');
            $table->text('failure_reason')->nullable()->after('failed_at');
            $table->json('provider_payload')->nullable()->after('failure_reason');
        });

        DB::connection('tenant')->table('payment_sessions')
            ->whereNull('amount_minor')
            ->orderBy('id')
            ->chunkById(100, function ($sessions): void {
                foreach ($sessions as $session) {
                    DB::connection('tenant')->table('payment_sessions')
                        ->where('id', $session->id)
                        ->update(['amount_minor' => (int) round(((float) $session->amount) * 100)]);
                }
            });
    }

    public function down(): void
    {
        // Rows created by the mobile flow have no legacy fee_id and cannot exist in the pre-mobile schema.
        DB::connection('tenant')->table('payment_sessions')->whereNull('fee_id')->delete();

        Schema::connection('tenant')->table('payment_sessions', function (Blueprint $table) {
            $table->dropForeign(['finance_invoice_id']);
            $table->dropForeign(['wallet_id']);
            $table->dropColumn([
                'finance_invoice_id', 'wallet_id', 'purpose', 'method', 'idempotency_key', 'amount_minor',
                'provider_reference', 'expires_at', 'paid_at', 'failed_at', 'failure_reason', 'provider_payload',
            ]);
            $table->unsignedBigInteger('fee_id')->nullable(false)->change();
        });
    }
};
