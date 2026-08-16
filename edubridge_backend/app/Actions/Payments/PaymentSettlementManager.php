<?php

namespace App\Actions\Payments;

use App\Actions\Wallet\WalletLedger;
use App\Models\Fee;
use App\Models\PaymentRefund;
use App\Models\PaymentSession;
use App\Models\Student;
use App\Support\Outbox;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaymentSettlementManager
{
    public function __construct(
        private readonly WalletLedger $walletLedger,
        private readonly Outbox $outbox,
    ) {}

    public function requestReceipt(PaymentSession $session): string
    {
        return $this->outbox->publishAfterCommit('receipt.generate_requested', ['payment_session_id' => (string) $session->id]);
    }

    public function refund(PaymentSession $session, float $amount, string $referenceId): PaymentRefund
    {
        if ($session->status !== PaymentSession::STATUS_PAID || $amount > (float) $session->amount) {
            throw new ConflictHttpException('Payment session is not refundable.');
        }

        $existing = PaymentRefund::query()->where('reference_id', $referenceId)->first();
        if ($existing !== null) {
            return $existing;
        }

        $fee = Fee::query()->findOrFail($session->fee_id);
        $student = Student::query()->findOrFail($fee->student_id);
        $this->walletLedger->reverseCredit($student, $session->currency, $amount, $referenceId, null);

        return PaymentRefund::query()->create([
            'payment_session_id' => $session->id,
            'amount' => $amount,
            'currency' => $session->currency,
            'status' => 'completed',
            'reference_id' => $referenceId,
        ])->refresh();
    }

    /** @return array{paid_sessions:int,refunds:int,net_amount:string} */
    public function dailyReconciliation(string $date): array
    {
        $paid = PaymentSession::query()->where('status', PaymentSession::STATUS_PAID)->whereDate('updated_at', $date)->get();
        $refunds = PaymentRefund::query()->whereDate('created_at', $date)->get();
        $net = $paid->sum(fn (PaymentSession $session): float => (float) $session->amount) - $refunds->sum(fn (PaymentRefund $refund): float => (float) $refund->amount);

        return ['paid_sessions' => $paid->count(), 'refunds' => $refunds->count(), 'net_amount' => number_format($net, 2, '.', '')];
    }
}
