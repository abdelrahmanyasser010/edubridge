<?php

namespace App\Infrastructure\Payments;

use App\Models\PaymentSession;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FakePaymentGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'fake';
    }

    public function clientConfiguration(PaymentSession $session): array
    {
        return [
            'provider' => 'fake',
            'configured' => true,
            'publishable_key' => 'pk_test_fake',
            'given_id' => $session->provider_session_id,
            'amount_minor' => (int) $session->amount_minor,
            'currency' => $session->currency,
            'method' => $session->method,
            'sdk_method' => $session->method,
            'callback_url' => 'edubridge://payments/return',
            'merchant_country_code' => 'SA',
        ];
    }

    public function verifyWebhook(array $payload): void
    {
        if (($payload['secret_token'] ?? null) !== 'test-webhook-secret') {
            throw new AccessDeniedHttpException('Invalid fake webhook secret.');
        }
    }

    public function fetchPayment(string $providerPaymentId): ?array
    {
        return null;
    }
}
