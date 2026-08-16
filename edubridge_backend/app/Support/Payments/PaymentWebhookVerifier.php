<?php

namespace App\Support\Payments;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaymentWebhookVerifier
{
    public function verify(string $payload, string $signature, string $timestamp, string $secret, int $toleranceSeconds = 300): void
    {
        $sentAt = Carbon::createFromTimestamp((int) $timestamp);

        if (abs(now()->diffInSeconds($sentAt, false)) > $toleranceSeconds) {
            throw new AccessDeniedHttpException('Webhook timestamp is outside tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        if (! hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Webhook signature is invalid.');
        }
    }

    /** @param array<string, mixed> $payload */
    public function reserveEvent(string $provider, string $eventId, array $payload): void
    {
        $inserted = DB::connection('tenant')->table('payment_webhook_events')->insertOrIgnore([
            'provider' => $provider,
            'event_id' => $eventId,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'status' => 'received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            throw new ConflictHttpException('Webhook event has already been received.');
        }
    }
}
