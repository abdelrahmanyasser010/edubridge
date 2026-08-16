<?php

namespace App\Actions\Wallet;

use App\Models\Student;
use App\Models\Wallet;
use App\Models\WalletPaymentToken;
use App\Models\WalletTransaction;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WalletLedger
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function walletFor(Student $student, string $currency): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['student_id' => $student->id],
            ['currency' => $currency, 'cached_balance' => 0, 'version' => 1],
        );
    }

    public function creditTopUp(Student $student, string $currency, float $amount, string $referenceId, ?int $actorCentralUserId): WalletTransaction
    {
        return DB::connection('tenant')->transaction(function () use ($student, $currency, $amount, $referenceId, $actorCentralUserId): WalletTransaction {
            $existing = WalletTransaction::query()->where('reference_type', 'top_up')->where('reference_id', $referenceId)->first();
            if ($existing !== null) {
                return $existing;
            }

            $wallet = $this->walletFor($student, $currency);
            $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $balanceAfter = (float) $wallet->cached_balance + $amount;

            $wallet->forceFill([
                'cached_balance' => $balanceAfter,
                'version' => $wallet->version + 1,
            ])->save();

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => WalletTransaction::TYPE_TOP_UP,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'reference_type' => 'top_up',
                'reference_id' => $referenceId,
                'actor_central_user_id' => $actorCentralUserId,
            ]);

            $this->audit->record('wallet.top_up_credited', WalletTransaction::class, (string) $transaction->id, null, ['wallet_id' => (string) $wallet->id, 'amount' => number_format($amount, 2, '.', '')]);

            return $transaction->refresh();
        });
    }

    /** @return array{token:string,model:WalletPaymentToken} */
    public function issuePaymentToken(Student $student, string $currency, float $maxAmount, int $ttlSeconds = 60, string $scope = 'canteen'): array
    {
        $wallet = $this->walletFor($student, $currency);
        $raw = Str::random(48);
        $model = WalletPaymentToken::query()->create([
            'wallet_id' => $wallet->id,
            'token_hash' => hash('sha256', $raw),
            'max_amount' => $maxAmount,
            'scope' => $scope,
            'expires_at' => now()->addSeconds($ttlSeconds),
        ]);

        return ['token' => $raw, 'model' => $model->refresh()];
    }

    public function deductByToken(string $rawToken, float $amount, string $referenceId, ?int $actorCentralUserId): WalletTransaction
    {
        return DB::connection('tenant')->transaction(function () use ($rawToken, $amount, $referenceId, $actorCentralUserId): WalletTransaction {
            $existing = WalletTransaction::query()->where('reference_type', 'pos_deduct')->where('reference_id', $referenceId)->first();
            if ($existing !== null) {
                return $existing;
            }

            $token = WalletPaymentToken::query()->where('token_hash', hash('sha256', $rawToken))->lockForUpdate()->first();

            if ($token === null || $token->used_at !== null || now()->greaterThan($token->expires_at) || $amount > (float) $token->max_amount) {
                throw new ConflictHttpException('Wallet payment token is not usable.');
            }

            $wallet = Wallet::query()->whereKey($token->wallet_id)->lockForUpdate()->firstOrFail();

            if ((float) $wallet->cached_balance < $amount) {
                throw new ConflictHttpException('Wallet balance is insufficient.');
            }

            $balanceAfter = (float) $wallet->cached_balance - $amount;
            $wallet->forceFill(['cached_balance' => $balanceAfter, 'version' => $wallet->version + 1])->save();
            $token->forceFill(['used_at' => now()])->save();

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => WalletTransaction::TYPE_DEDUCT,
                'amount' => -1 * $amount,
                'balance_after' => $balanceAfter,
                'reference_type' => 'pos_deduct',
                'reference_id' => $referenceId,
                'actor_central_user_id' => $actorCentralUserId,
            ]);

            $this->audit->record('wallet.pos_deducted', WalletTransaction::class, (string) $transaction->id, null, ['wallet_id' => (string) $wallet->id, 'amount' => number_format($amount, 2, '.', '')]);

            return $transaction->refresh();
        });
    }

    public function reverseCredit(Student $student, string $currency, float $amount, string $referenceId, ?int $actorCentralUserId): WalletTransaction
    {
        return DB::connection('tenant')->transaction(function () use ($student, $currency, $amount, $referenceId, $actorCentralUserId): WalletTransaction {
            $existing = WalletTransaction::query()->where('reference_type', 'refund_reversal')->where('reference_id', $referenceId)->first();
            if ($existing !== null) {
                return $existing;
            }

            $wallet = Wallet::query()->where('student_id', $student->id)->where('currency', $currency)->lockForUpdate()->firstOrFail();
            $balanceAfter = (float) $wallet->cached_balance - $amount;
            $wallet->forceFill(['cached_balance' => $balanceAfter, 'version' => $wallet->version + 1])->save();

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'type' => WalletTransaction::TYPE_REFUND_REVERSAL,
                'amount' => -1 * $amount,
                'balance_after' => $balanceAfter,
                'reference_type' => 'refund_reversal',
                'reference_id' => $referenceId,
                'actor_central_user_id' => $actorCentralUserId,
            ]);

            $this->audit->record('wallet.refund_reversed', WalletTransaction::class, (string) $transaction->id, null, ['wallet_id' => (string) $wallet->id]);

            return $transaction->refresh();
        });
    }
}
