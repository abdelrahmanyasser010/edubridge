<?php

namespace App\Infrastructure\Payments;

use App\Models\PaymentSession;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class MoyasarPaymentGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'moyasar';
    }

    public function clientConfiguration(PaymentSession $session): array
    {
        $publishableKey = config('payments.moyasar.publishable_key');

        return [
            'provider' => 'moyasar',
            'configured' => is_string($publishableKey) && $publishableKey !== '',
            'publishable_key' => $publishableKey,
            'given_id' => $session->provider_session_id,
            'amount_minor' => (int) $session->amount_minor,
            'currency' => $session->currency,
            'callback_url' => config('payments.moyasar.callback_url'),
            'method' => $session->method,
            'supported_networks' => $this->supportedNetworks($session->method),
            'sdk_method' => $this->sdkMethod($session->method),
            'merchant_country_code' => 'SA',
        ];
    }

    public function verifyWebhook(array $payload): void
    {
        $expected = config('payments.moyasar.webhook_secret');
        $presented = $payload['secret_token'] ?? null;

        if (! is_string($expected) || $expected === '') {
            throw new ServiceUnavailableHttpException(null, 'Payment webhook secret is not configured.');
        }

        if (! is_string($presented) || ! hash_equals($expected, $presented)) {
            throw new AccessDeniedHttpException('Invalid payment webhook secret.');
        }
    }

    public function fetchPayment(string $providerPaymentId): ?array
    {
        $secret = config('payments.moyasar.secret_key');
        $base = rtrim((string) config('payments.moyasar.api_url'), '/');

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $response = Http::acceptJson()
            ->withBasicAuth($secret, '')
            ->connectTimeout(5)
            ->timeout(10)
            ->get($base.'/payments/'.$providerPaymentId);

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        return $response->json();
    }

    /** @return list<string> */
    private function supportedNetworks(?string $method): array
    {
        return match ($method) {
            'mada' => ['mada'],
            'visa' => ['visa'],
            'mastercard' => ['mastercard'],
            default => ['mada', 'visa', 'mastercard'],
        };
    }

    private function sdkMethod(?string $method): string
    {
        return match ($method) {
            'apple_pay' => 'applepay',
            'stc_pay' => 'stcpay',
            'samsung_pay' => 'samsungpay',
            default => 'creditcard',
        };
    }
}
