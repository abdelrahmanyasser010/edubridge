<?php

namespace App\Actions\Payments;

use App\Actions\Wallet\WalletLedger;
use App\Models\Fee;
use App\Models\PaymentSession;
use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\Payments\PaymentWebhookVerifier;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentSessionManager
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PaymentWebhookVerifier $verifier,
        private readonly WalletLedger $walletLedger,
    ) {}

    public function createFee(Student $student, string $title, float $amount, string $currency): Fee
    {
        return Fee::query()->create([
            'student_id' => $student->id,
            'title' => $title,
            'amount' => $amount,
            'currency' => $currency,
            'status' => Fee::STATUS_OPEN,
        ])->refresh();
    }

    public function createSession(Fee $fee, string $provider, string $providerSessionId): PaymentSession
    {
        if ($fee->status !== Fee::STATUS_OPEN) {
            throw new ConflictHttpException('Fee is not payable.');
        }

        return PaymentSession::query()->create([
            'fee_id' => $fee->id,
            'provider' => $provider,
            'provider_session_id' => $providerSessionId,
            'amount' => $fee->amount,
            'currency' => $fee->currency,
            'status' => PaymentSession::STATUS_PENDING,
        ])->refresh();
    }

    /** @param array{id:string,provider_session_id:string,status:string,amount:numeric,currency:string} $event */
    public function handlePaidWebhook(array $event, string $rawPayload, string $signature, string $timestamp, string $secret): PaymentSession
    {
        $this->verifier->verify($rawPayload, $signature, $timestamp, $secret);
        $this->verifier->reserveEvent('moyasar', $event['id'], $event);

        return DB::connection('tenant')->transaction(function () use ($event): PaymentSession {
            $session = PaymentSession::query()->where('provider_session_id', $event['provider_session_id'])->lockForUpdate()->first();

            if ($session === null) {
                throw new NotFoundHttpException;
            }

            if ($session->status === PaymentSession::STATUS_PAID) {
                return $session;
            }

            if ($event['status'] !== 'paid' || (float) $event['amount'] !== (float) $session->amount || $event['currency'] !== $session->currency) {
                throw new ConflictHttpException('Payment event does not match payment session.');
            }

            $fee = Fee::query()->whereKey($session->fee_id)->lockForUpdate()->firstOrFail();
            $fee->forceFill(['status' => Fee::STATUS_PAID])->save();
            $session->forceFill(['status' => PaymentSession::STATUS_PAID])->save();
            $student = Student::query()->findOrFail($fee->student_id);
            $this->walletLedger->creditTopUp($student, $session->currency, (float) $session->amount, 'payment_session:'.$session->id, null);
            $this->audit->record('payment_session.paid', PaymentSession::class, (string) $session->id, null, ['fee_id' => (string) $fee->id]);

            return $session->refresh();
        });
    }
}
