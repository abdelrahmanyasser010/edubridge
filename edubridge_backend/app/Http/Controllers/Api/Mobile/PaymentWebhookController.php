<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\MobilePaymentManager;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentWebhookController
{
    public function __invoke(Request $request, string $provider, MobilePaymentManager $manager): JsonResponse
    {
        if ($provider !== app(\App\Infrastructure\Payments\PaymentGateway::class)->provider()) {
            throw new NotFoundHttpException;
        }

        return ApiResponse::data($manager->handleWebhook($request->all()));
    }
}
