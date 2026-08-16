<?php

namespace App\Infrastructure\Payments;

use App\Models\PaymentSession;

interface PaymentGateway
{
    public function provider(): string;

    /** @return array<string, mixed> */
    public function clientConfiguration(PaymentSession $session): array;

    /** @param array<string, mixed> $payload */
    public function verifyWebhook(array $payload): void;

    /** @return array<string, mixed>|null */
    public function fetchPayment(string $providerPaymentId): ?array;
}
