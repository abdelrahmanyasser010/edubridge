<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\MobilePaymentManager;
use App\Http\Requests\Mobile\CreatePaymentSessionRequest;
use App\Http\Requests\Mobile\CreateWalletTopUpRequest;
use App\Models\FinanceInvoice;
use App\Models\PaymentSession;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MobilePaymentController
{
    public function methods(MobilePaymentManager $manager): JsonResponse
    {
        Gate::authorize('payment.view');

        return ApiResponse::data($manager->methods());
    }

    public function createInvoiceSession(CreatePaymentSessionRequest $request, int $student, int $invoice, MobilePaymentManager $manager): JsonResponse
    {
        Gate::authorize('payment.collect');
        $user = $request->user() ?? throw new AuthenticationException;
        $data = $request->validated();
        $session = $manager->createInvoiceSession(
            Student::query()->findOrFail($student),
            FinanceInvoice::query()->findOrFail($invoice),
            (int) $user->id,
            $data['method'],
            $data['idempotency_key'],
        );

        return ApiResponse::data($manager->sessionPayload($session), Response::HTTP_CREATED);
    }

    public function createWalletTopUp(CreateWalletTopUpRequest $request, int $student, MobilePaymentManager $manager): JsonResponse
    {
        Gate::authorize('payment.collect');
        $user = $request->user() ?? throw new AuthenticationException;
        $data = $request->validated();
        $session = $manager->createWalletTopUpSession(
            Student::query()->findOrFail($student),
            (int) $user->id,
            (int) $data['amount_minor'],
            $data['payment_method'],
            $data['idempotency_key'],
        );

        return ApiResponse::data($manager->sessionPayload($session), Response::HTTP_CREATED);
    }

    public function show(Request $request, int $payment, MobilePaymentManager $manager): JsonResponse
    {
        Gate::authorize('payment.view');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->sessionPayload($manager->ownedSession($payment, (int) $user->id)));
    }

    public function receipt(Request $request, int $payment, MobilePaymentManager $manager): JsonResponse
    {
        Gate::authorize('payment.view');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->receipt(PaymentSession::query()->findOrFail($payment), (int) $user->id));
    }
}
