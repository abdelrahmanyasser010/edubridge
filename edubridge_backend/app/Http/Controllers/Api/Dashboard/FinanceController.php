<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Actions\Finance\FinanceDashboardManager;
use App\Http\Requests\Dashboard\Finance\FinanceDiscountFilterRequest;
use App\Http\Requests\Dashboard\Finance\FinanceInvoiceFilterRequest;
use App\Http\Requests\Dashboard\Finance\FinancePaymentFilterRequest;
use App\Http\Requests\Dashboard\Finance\FinanceRefundFilterRequest;
use App\Http\Requests\Dashboard\Finance\FinanceReportRequest;
use App\Http\Requests\Dashboard\Finance\StoreFinanceDiscountRequest;
use App\Http\Requests\Dashboard\Finance\StoreFinanceInvoiceRequest;
use App\Http\Requests\Dashboard\Finance\StoreFinancePaymentRequest;
use App\Http\Requests\Dashboard\Finance\StoreFinanceRefundRequest;
use App\Http\Requests\Dashboard\Finance\UpdateFinanceDiscountRequest;
use App\Http\Requests\Dashboard\Finance\UpdateFinanceInvoiceRequest;
use App\Http\Resources\Dashboard\Finance\FinanceDiscountResource;
use App\Http\Resources\Dashboard\Finance\FinanceInvoiceResource;
use App\Http\Resources\Dashboard\Finance\FinancePaymentResource;
use App\Http\Resources\Dashboard\Finance\FinanceRefundResource;
use App\Models\FinanceDiscount;
use App\Models\FinanceInvoice;
use App\Models\FinancePayment;
use App\Models\Student;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class FinanceController
{
    public function summary(FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('viewAny', FinanceInvoice::class);

        return ApiResponse::data($manager->summary());
    }

    public function invoices(FinanceInvoiceFilterRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('viewAny', FinanceInvoice::class);
        $invoices = $manager->invoices($request->validated());

        return ApiResponse::data(
            FinanceInvoiceResource::collection($invoices->items())->resolve($request),
            meta: $this->paginationMeta($invoices),
        );
    }

    public function storeInvoice(StoreFinanceInvoiceRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('create', FinanceInvoice::class);

        return ApiResponse::data(
            (new FinanceInvoiceResource($manager->createInvoice($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function showInvoice(int $invoice): JsonResponse
    {
        $invoice = FinanceInvoice::query()->with(['student', 'lines'])->findOrFail($invoice);
        Gate::authorize('view', $invoice);

        return ApiResponse::data((new FinanceInvoiceResource($invoice))->resolve());
    }

    public function updateInvoice(UpdateFinanceInvoiceRequest $request, int $invoice, FinanceDashboardManager $manager): JsonResponse
    {
        $invoice = FinanceInvoice::query()->with('lines')->findOrFail($invoice);
        Gate::authorize('update', $invoice);

        return ApiResponse::data(
            (new FinanceInvoiceResource($manager->updateInvoice($invoice, $request->validated())))->resolve($request),
        );
    }

    public function destroyInvoice(int $invoice, FinanceDashboardManager $manager): JsonResponse
    {
        $invoice = FinanceInvoice::query()->findOrFail($invoice);
        Gate::authorize('delete', $invoice);

        return ApiResponse::data((new FinanceInvoiceResource($manager->cancelInvoice($invoice)))->resolve());
    }

    public function payments(FinancePaymentFilterRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('viewAny', FinancePayment::class);
        $payments = $manager->payments($request->validated());

        return ApiResponse::data(
            FinancePaymentResource::collection($payments->items())->resolve($request),
            meta: $this->paginationMeta($payments),
        );
    }

    public function storePayment(StoreFinancePaymentRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('finance.payments.record');
        $user = $request->user();

        return ApiResponse::data(
            (new FinancePaymentResource($manager->recordPayment($request->validated(), $user?->id)))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function showPayment(int $payment): JsonResponse
    {
        $payment = FinancePayment::query()->with('invoice')->findOrFail($payment);
        Gate::authorize('view', $payment);

        return ApiResponse::data((new FinancePaymentResource($payment))->resolve());
    }

    public function refunds(FinanceRefundFilterRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('finance.view');
        $refunds = $manager->refunds($request->validated());

        return ApiResponse::data(
            FinanceRefundResource::collection($refunds->items())->resolve($request),
            meta: $this->paginationMeta($refunds),
        );
    }

    public function storeRefund(StoreFinanceRefundRequest $request, int $payment, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('payment.refund');
        $financePayment = FinancePayment::query()->findOrFail($payment);

        return ApiResponse::data(
            (new FinanceRefundResource($manager->refundPayment($financePayment, $request->validated(), $request->user()?->id)))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function discounts(FinanceDiscountFilterRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('viewAny', FinanceDiscount::class);
        $discounts = $manager->discounts($request->validated());

        return ApiResponse::data(
            FinanceDiscountResource::collection($discounts->items())->resolve($request),
            meta: $this->paginationMeta($discounts),
        );
    }

    public function storeDiscount(StoreFinanceDiscountRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('create', FinanceDiscount::class);

        return ApiResponse::data(
            (new FinanceDiscountResource($manager->createDiscount($request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function updateDiscount(UpdateFinanceDiscountRequest $request, int $discount, FinanceDashboardManager $manager): JsonResponse
    {
        $discount = FinanceDiscount::query()->findOrFail($discount);
        Gate::authorize('update', $discount);

        return ApiResponse::data(
            (new FinanceDiscountResource($manager->updateDiscount($discount, $request->validated())))->resolve($request),
        );
    }

    public function destroyDiscount(int $discount, FinanceDashboardManager $manager): JsonResponse
    {
        $discount = FinanceDiscount::query()->findOrFail($discount);
        Gate::authorize('delete', $discount);

        return ApiResponse::data((new FinanceDiscountResource($manager->archiveDiscount($discount)))->resolve());
    }

    public function collectionsReport(FinanceReportRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return ApiResponse::data($manager->collectionsReport($request->validated()));
    }

    public function outstandingReport(FinanceReportRequest $request, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return ApiResponse::data($manager->outstandingReport($request->validated()));
    }

    public function studentStatement(int $student, FinanceDashboardManager $manager): JsonResponse
    {
        Gate::authorize('finance.reports.view');

        return ApiResponse::data($manager->studentStatement(Student::query()->findOrFail($student)));
    }

    /**
     * @return array<string, mixed>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
