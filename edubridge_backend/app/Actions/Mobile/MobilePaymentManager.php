<?php

namespace App\Actions\Mobile;

use App\Actions\Wallet\WalletLedger;
use App\Infrastructure\Payments\PaymentGateway;
use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\PaymentSession;
use App\Models\Student;
use App\Models\Wallet;
use App\Support\AuditLogger;
use App\Support\Money;
use App\Support\ParentStudentAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MobilePaymentManager
{
    public function __construct(
        private readonly ParentStudentAccess $access,
        private readonly PaymentGateway $gateway,
        private readonly WalletLedger $walletLedger,
        private readonly AuditLogger $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function methods(): array
    {
        $labels = [
            'mada' => 'mada',
            'apple_pay' => 'Apple Pay',
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'stc_pay' => 'STC Pay',
            'samsung_pay' => 'Samsung Pay',
        ];

        return collect((array) config('payments.methods', []))
            ->filter(fn (mixed $enabled): bool => (bool) $enabled)
            ->map(fn (mixed $enabled, string $key): array => [
                'key' => $key,
                'label' => $labels[$key] ?? $key,
                'enabled' => true,
                'provider' => $this->gateway->provider(),
            ])->values()->all();
    }

    public function createInvoiceSession(Student $student, FinanceInvoice $invoice, int $centralUserId, string $method, string $idempotencyKey): PaymentSession
    {
        $student = $this->access->student($student->id, $centralUserId);
        $this->ensureMethod($method);

        if ($invoice->student_id !== $student->id) {
            throw new NotFoundHttpException;
        }

        return DB::connection('tenant')->transaction(function () use ($invoice, $method, $idempotencyKey): PaymentSession {
            $invoice = FinanceInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($invoice->status, [FinanceInvoice::STATUS_CANCELLED, FinanceInvoice::STATUS_PAID], true)) {
                throw new ConflictHttpException('Invoice is not payable.');
            }

            $due = max(0, (float) $invoice->total - (float) $invoice->paid_total);
            if ($due <= 0) {
                throw new ConflictHttpException('Invoice has no outstanding balance.');
            }

            $amountMinor = Money::toMinor($due, $invoice->currency);

            $existing = PaymentSession::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->purpose !== PaymentSession::PURPOSE_INVOICE
                    || $existing->finance_invoice_id !== $invoice->id
                    || $existing->method !== $method
                    || (int) $existing->amount_minor !== $amountMinor) {
                    throw new ConflictHttpException('Idempotency key was already used with a different payment request.');
                }

                return $existing;
            }

            $givenId = (string) Str::uuid();
            $session = PaymentSession::query()->create([
                'fee_id' => null,
                'finance_invoice_id' => $invoice->id,
                'wallet_id' => null,
                'provider' => $this->gateway->provider(),
                'provider_session_id' => $givenId,
                'purpose' => PaymentSession::PURPOSE_INVOICE,
                'method' => $method,
                'idempotency_key' => $idempotencyKey,
                'amount' => $due,
                'amount_minor' => $amountMinor,
                'currency' => $invoice->currency,
                'status' => PaymentSession::STATUS_INITIATED,
                'expires_at' => now()->addMinutes((int) config('payments.session_ttl_minutes', 30)),
            ]);

            $this->audit->record('mobile.payment_session.created', PaymentSession::class, (string) $session->id, null, [
                'purpose' => $session->purpose,
                'invoice_id' => (string) $invoice->id,
                'method' => $method,
                'amount_minor' => $amountMinor,
            ]);

            return $session->refresh();
        });
    }

    public function createWalletTopUpSession(Student $student, int $centralUserId, int $amountMinor, string $method, string $idempotencyKey): PaymentSession
    {
        $student = $this->access->student($student->id, $centralUserId);
        $this->ensureMethod($method);

        $min = (int) config('payments.wallet.top_up_min_minor', 1000);
        $max = (int) config('payments.wallet.top_up_max_minor', 100000);
        if ($amountMinor < $min || $amountMinor > $max) {
            throw new ConflictHttpException('Wallet top-up amount is outside the allowed range.');
        }

        $currency = (string) config('payments.currency', 'SAR');
        $wallet = $this->walletLedger->walletFor($student, $currency);

        return DB::connection('tenant')->transaction(function () use ($wallet, $amountMinor, $method, $idempotencyKey, $currency): PaymentSession {
            $existing = PaymentSession::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                if ($existing->purpose !== PaymentSession::PURPOSE_WALLET_TOP_UP
                    || $existing->wallet_id !== $wallet->id
                    || $existing->method !== $method
                    || (int) $existing->amount_minor !== $amountMinor) {
                    throw new ConflictHttpException('Idempotency key was already used with a different payment request.');
                }

                return $existing;
            }

            $givenId = (string) Str::uuid();
            $session = PaymentSession::query()->create([
                'fee_id' => null,
                'finance_invoice_id' => null,
                'wallet_id' => $wallet->id,
                'provider' => $this->gateway->provider(),
                'provider_session_id' => $givenId,
                'purpose' => PaymentSession::PURPOSE_WALLET_TOP_UP,
                'method' => $method,
                'idempotency_key' => $idempotencyKey,
                'amount' => Money::fromMinor($amountMinor, $currency),
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => PaymentSession::STATUS_INITIATED,
                'expires_at' => now()->addMinutes((int) config('payments.session_ttl_minutes', 30)),
            ]);

            $this->audit->record('mobile.wallet_top_up_session.created', PaymentSession::class, (string) $session->id, null, [
                'wallet_id' => (string) $wallet->id,
                'method' => $method,
                'amount_minor' => $amountMinor,
            ]);

            return $session->refresh();
        });
    }

    /** @return array<string, mixed> */
    public function sessionPayload(PaymentSession $session): array
    {
        return [
            'id' => (string) $session->id,
            'provider_payment_id' => $session->provider_session_id,
            'purpose' => $session->purpose,
            'status' => $session->status,
            'method' => $session->method,
            'amount_minor' => (int) ($session->amount_minor ?? Money::toMinor($session->amount, $session->currency)),
            'currency' => $session->currency,
            'invoice_id' => $session->finance_invoice_id === null ? null : (string) $session->finance_invoice_id,
            'wallet_id' => $session->wallet_id === null ? null : (string) $session->wallet_id,
            'expires_at' => $session->expires_at?->toJSON(),
            'paid_at' => $session->paid_at?->toJSON(),
            'failure_reason' => $session->failure_reason,
            'provider_config' => $this->gateway->clientConfiguration($session),
        ];
    }

    public function ownedSession(int $sessionId, int $centralUserId): PaymentSession
    {
        $session = PaymentSession::query()->with(['invoice', 'wallet'])->findOrFail($sessionId);
        $studentId = $session->invoice?->student_id;

        if ($studentId === null && $session->wallet_id !== null) {
            $studentId = $session->wallet?->student_id;
        }

        if ($studentId === null) {
            throw new NotFoundHttpException;
        }

        $this->access->student((int) $studentId, $centralUserId);

        return $session;
    }

    /** @return array<string, mixed> */
    public function receipt(PaymentSession $session, int $centralUserId): array
    {
        $session = $this->ownedSession($session->id, $centralUserId);
        if ($session->status !== PaymentSession::STATUS_PAID) {
            throw new ConflictHttpException('Receipt is available only for paid payments.');
        }

        $invoice = $session->invoice;

        return [
            'receipt_number' => 'PAY-'.str_pad((string) $session->id, 8, '0', STR_PAD_LEFT),
            'payment_id' => (string) $session->id,
            'provider_reference' => $session->provider_reference ?? $session->provider_session_id,
            'method' => $session->method,
            'amount_minor' => (int) $session->amount_minor,
            'currency' => $session->currency,
            'paid_at' => $session->paid_at?->toJSON(),
            'invoice' => $invoice === null ? null : [
                'id' => (string) $invoice->id,
                'number' => $invoice->invoice_number,
            ],
        ];
    }

    /** @param array<string, mixed> $payload @return array{duplicate:bool,processed:bool,status:string} */
    public function handleWebhook(array $payload): array
    {
        $this->gateway->verifyWebhook($payload);

        $eventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? null;
        $data = $payload['data'] ?? null;

        if (! is_string($eventId) || $eventId === '' || ! is_string($eventType) || ! is_array($data)) {
            throw new ConflictHttpException('Invalid payment webhook payload.');
        }

        $inserted = DB::connection('tenant')->table('payment_webhook_events')->insertOrIgnore([
            'provider' => $this->gateway->provider(),
            'event_id' => $eventId,
            'payload' => json_encode($this->sanitizedWebhookPayload($payload), JSON_THROW_ON_ERROR),
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            $existingStatus = DB::connection('tenant')->table('payment_webhook_events')
                ->where('provider', $this->gateway->provider())
                ->where('event_id', $eventId)
                ->value('status');

            if (in_array($existingStatus, ['processed', 'ignored', 'requires_reconciliation'], true)) {
                return [
                    'duplicate' => true,
                    'processed' => $existingStatus === 'processed',
                    'status' => (string) $existingStatus,
                ];
            }
        }

        $providerPaymentId = $data['id'] ?? null;
        if (! is_string($providerPaymentId) || $providerPaymentId === '') {
            $this->markWebhook($eventId, 'ignored');

            return ['duplicate' => false, 'processed' => false, 'status' => 'ignored'];
        }

        $session = PaymentSession::query()->where('provider_session_id', $providerPaymentId)->first();
        if ($session === null) {
            $this->markWebhook($eventId, 'ignored');

            return ['duplicate' => false, 'processed' => false, 'status' => 'unknown_payment'];
        }

        return DB::connection('tenant')->transaction(function () use ($eventId, $eventType, $data, $session): array {
            $eventStatus = DB::connection('tenant')->table('payment_webhook_events')
                ->where('provider', $this->gateway->provider())
                ->where('event_id', $eventId)
                ->lockForUpdate()
                ->value('status');

            if (in_array($eventStatus, ['processed', 'ignored', 'requires_reconciliation'], true)) {
                return [
                    'duplicate' => true,
                    'processed' => $eventStatus === 'processed',
                    'status' => (string) $eventStatus,
                ];
            }

            $session = PaymentSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            try {
                $this->validateProviderAmounts($session, $data);
            } catch (ConflictHttpException $exception) {
                $session->forceFill([
                    'provider_payload' => $this->sanitizedProviderPayload($data),
                    'failure_reason' => $exception->getMessage(),
                ])->save();
                $this->markWebhook($eventId, 'requires_reconciliation');
                $this->audit->record('mobile.payment.amount_reconciliation_required', PaymentSession::class, (string) $session->id, null, [
                    'provider_event_id' => $eventId,
                    'reason' => $exception->getMessage(),
                ]);

                return ['duplicate' => false, 'processed' => false, 'status' => 'requires_reconciliation'];
            }

            if (in_array($eventType, ['payment_paid', 'payment_captured'], true)) {
                if (! $this->invoiceOutstandingMatchesSession($session)) {
                    $session->forceFill([
                        'provider_payload' => $this->sanitizedProviderPayload($data),
                        'failure_reason' => 'Invoice outstanding balance changed after payment session creation; reconciliation is required.',
                    ])->save();
                    $this->markWebhook($eventId, 'requires_reconciliation');
                    $this->audit->record('mobile.payment.invoice_reconciliation_required', PaymentSession::class, (string) $session->id, null, [
                        'provider_event_id' => $eventId,
                        'invoice_id' => $session->finance_invoice_id === null ? null : (string) $session->finance_invoice_id,
                    ]);

                    return ['duplicate' => false, 'processed' => false, 'status' => 'requires_reconciliation'];
                }

                $this->settlePaid($session, $data);
            } elseif (in_array($eventType, ['payment_faild', 'payment_failed', 'payment_abandoned'], true)) {
                if ($session->status !== PaymentSession::STATUS_PAID) {
                    $source = is_array($data['source'] ?? null) ? $data['source'] : [];
                    $session->forceFill([
                        'status' => PaymentSession::STATUS_FAILED,
                        'failed_at' => now(),
                        'failure_reason' => (string) ($source['message'] ?? $data['message'] ?? 'Payment failed'),
                        'provider_payload' => $this->sanitizedProviderPayload($data),
                    ])->save();
                }
            } elseif ($eventType === 'payment_voided') {
                if ($session->status !== PaymentSession::STATUS_PAID) {
                    $session->forceFill(['status' => PaymentSession::STATUS_CANCELLED, 'provider_payload' => $this->sanitizedProviderPayload($data)])->save();
                }
            } elseif ($eventType === 'payment_refunded') {
                // A provider refund event alone is not enough to mutate the local finance or wallet ledger.
                // Refund accounting is reconciled through the existing administrative refund workflow,
                // otherwise invoice paid_total / wallet balance could become inconsistent.
                $session->forceFill([
                    'provider_payload' => $this->sanitizedProviderPayload($data),
                    'failure_reason' => 'Provider refund event received; administrative reconciliation is required.',
                ])->save();
                $this->markWebhook($eventId, 'requires_reconciliation');
                $this->audit->record('mobile.payment.refund_reconciliation_required', PaymentSession::class, (string) $session->id, null, [
                    'provider_event_id' => $eventId,
                    'provider_reference' => (string) ($data['id'] ?? $session->provider_session_id),
                ]);

                return ['duplicate' => false, 'processed' => false, 'status' => 'requires_reconciliation'];
            } else {
                $this->markWebhook($eventId, 'ignored');

                return ['duplicate' => false, 'processed' => false, 'status' => 'ignored'];
            }

            $this->markWebhook($eventId, 'processed');

            return ['duplicate' => false, 'processed' => true, 'status' => $session->refresh()->status];
        });
    }

    private function settlePaid(PaymentSession $session, array $data): void
    {
        if ($session->status === PaymentSession::STATUS_PAID) {
            return;
        }

        if ($session->purpose === PaymentSession::PURPOSE_INVOICE) {
            $invoice = FinanceInvoice::query()->whereKey($session->finance_invoice_id)->lockForUpdate()->firstOrFail();
            $already = FinancePayment::query()->where('reference', $session->provider_session_id)->first();

            if ($already === null) {
                $amount = ((int) $session->amount_minor) / Money::minorFactor($session->currency);
                FinancePayment::query()->create([
                    'finance_invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'method' => $session->method ?? $this->providerMethod($data),
                    'paid_at' => now(),
                    'reference' => $session->provider_session_id,
                    'notes' => 'Online payment via '.$session->provider,
                    'recorded_by_central_user_id' => null,
                ]);

                $newPaid = round((float) $invoice->paid_total + $amount, 2);
                $status = $newPaid >= (float) $invoice->total ? FinanceInvoice::STATUS_PAID : FinanceInvoice::STATUS_PARTIAL;
                $invoice->forceFill(['paid_total' => min($newPaid, (float) $invoice->total), 'status' => $status])->save();

                if ($status === FinanceInvoice::STATUS_PAID) {
                    DB::connection('tenant')->table('activity_registrations')
                        ->where('finance_invoice_id', $invoice->id)
                        ->where('status', 'awaiting_payment')
                        ->update(['status' => 'confirmed', 'updated_at' => now()]);
                }
            }
        } elseif ($session->purpose === PaymentSession::PURPOSE_WALLET_TOP_UP) {
            $wallet = Wallet::query()->whereKey($session->wallet_id)->lockForUpdate()->firstOrFail();
            $student = Student::query()->findOrFail($wallet->student_id);
            $this->walletLedger->creditTopUp(
                $student,
                $session->currency,
                ((int) $session->amount_minor) / Money::minorFactor($session->currency),
                'mobile_payment_session:'.$session->id,
                null,
            );
        }

        $session->forceFill([
            'status' => PaymentSession::STATUS_PAID,
            'paid_at' => now(),
            'provider_reference' => (string) ($data['id'] ?? $session->provider_session_id),
            'provider_payload' => $this->sanitizedProviderPayload($data),
            'failure_reason' => null,
            'failed_at' => null,
        ])->save();

        $this->audit->record('mobile.payment.paid', PaymentSession::class, (string) $session->id, null, [
            'purpose' => $session->purpose,
            'amount_minor' => (int) $session->amount_minor,
            'currency' => $session->currency,
            'method' => $session->method,
        ]);
    }

    private function invoiceOutstandingMatchesSession(PaymentSession $session): bool
    {
        if ($session->purpose !== PaymentSession::PURPOSE_INVOICE || $session->finance_invoice_id === null) {
            return true;
        }

        $invoice = FinanceInvoice::query()->whereKey($session->finance_invoice_id)->lockForUpdate()->first();
        if ($invoice === null || in_array($invoice->status, [FinanceInvoice::STATUS_CANCELLED, FinanceInvoice::STATUS_PAID], true)) {
            return false;
        }

        $currentDueMinor = Money::toMinor(
            max(0, (float) $invoice->total - (float) $invoice->paid_total),
            $invoice->currency,
        );

        return $currentDueMinor === (int) $session->amount_minor;
    }

    private function validateProviderAmounts(PaymentSession $session, array $data): void
    {
        if (! array_key_exists('amount', $data) || (int) $data['amount'] !== (int) $session->amount_minor) {
            throw new ConflictHttpException('Payment provider amount does not match the server payment session.');
        }

        if (! isset($data['currency']) || strtoupper((string) $data['currency']) !== strtoupper($session->currency)) {
            throw new ConflictHttpException('Payment provider currency does not match the server payment session.');
        }
    }

    private function ensureMethod(string $method): void
    {
        if (! (bool) config('payments.methods.'.$method, false)) {
            throw new ConflictHttpException('Payment method is not enabled.');
        }
    }

    private function markWebhook(string $eventId, string $status): void
    {
        DB::connection('tenant')->table('payment_webhook_events')
            ->where('provider', $this->gateway->provider())
            ->where('event_id', $eventId)
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    /** @return array<string, mixed> */
    private function sanitizedWebhookPayload(array $payload): array
    {
        return [
            'id' => $payload['id'] ?? null,
            'type' => $payload['type'] ?? null,
            'created_at' => $payload['created_at'] ?? null,
            'account_name' => $payload['account_name'] ?? null,
            'live' => $payload['live'] ?? null,
            'data' => is_array($payload['data'] ?? null) ? $this->sanitizedProviderPayload($payload['data']) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function sanitizedProviderPayload(array $data): array
    {
        $source = is_array($data['source'] ?? null) ? $data['source'] : [];

        return [
            'id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => $data['currency'] ?? null,
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
            'source' => [
                'type' => $source['type'] ?? null,
                'company' => $source['company'] ?? null,
                'number' => $source['number'] ?? null,
                'message' => $source['message'] ?? null,
                'reference_number' => $source['reference_number'] ?? null,
            ],
        ];
    }

    private function providerMethod(array $data): string
    {
        $source = is_array($data['source'] ?? null) ? $data['source'] : [];
        $type = (string) ($source['type'] ?? 'creditcard');
        $company = (string) ($source['company'] ?? '');

        return match ($type) {
            'applepay' => 'apple_pay',
            'stcpay' => 'stc_pay',
            'samsungpay' => 'samsung_pay',
            default => match ($company) {
                'mada' => 'mada',
                'visa' => 'visa',
                'master' => 'mastercard',
                default => 'card',
            },
        };
    }
}
