<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Mobile\ParentFinanceManager;
use App\Http\Requests\Mobile\CreateWalletPaymentTokenRequest;
use App\Http\Requests\Mobile\InvoiceFilterRequest;
use App\Models\FinanceInvoice;
use App\Models\Student;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ParentFinanceController
{
    public function summary(Request $request, int $student, ParentFinanceManager $manager): JsonResponse
    {
        Gate::authorize('payment.view');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->summary(Student::query()->findOrFail($student), (int) $user->id));
    }

    public function invoices(InvoiceFilterRequest $request, int $student, ParentFinanceManager $manager): JsonResponse
    {
        Gate::authorize('payment.view');
        $user = $request->user() ?? throw new AuthenticationException;
        $paginator = $manager->invoices(Student::query()->findOrFail($student), (int) $user->id, $request->validated());

        return ApiResponse::data(
            collect($paginator->items())->map(fn (FinanceInvoice $invoice): array => $manager->invoicePayload($invoice))->all(),
            meta: $this->paginationMeta($paginator),
        );
    }

    public function invoice(Request $request, int $student, int $invoice, ParentFinanceManager $manager): JsonResponse
    {
        Gate::authorize('payment.view');
        $user = $request->user() ?? throw new AuthenticationException;
        $model = $manager->invoice(
            Student::query()->findOrFail($student),
            FinanceInvoice::query()->findOrFail($invoice),
            (int) $user->id,
        );

        return ApiResponse::data($manager->invoicePayload($model));
    }

    public function wallet(Request $request, int $student, ParentFinanceManager $manager): JsonResponse
    {
        Gate::authorize('wallet.view');
        $user = $request->user() ?? throw new AuthenticationException;

        return ApiResponse::data($manager->wallet(Student::query()->findOrFail($student), (int) $user->id));
    }

    public function walletTransactions(Request $request, int $student, ParentFinanceManager $manager): JsonResponse
    {
        Gate::authorize('wallet.view');
        $user = $request->user() ?? throw new AuthenticationException;
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));
        $paginator = $manager->walletTransactions(Student::query()->findOrFail($student), (int) $user->id, $perPage);
        $currency = (string) config('payments.currency', 'SAR');

        $items = collect($paginator->items())->map(fn ($row): array => [
            'id' => (string) $row->id,
            'type' => $row->type,
            'amount_minor' => Money::toMinor($row->amount, $currency),
            'balance_after_minor' => Money::toMinor($row->balance_after, $currency),
            'reference_type' => $row->reference_type,
            'reference_id' => $row->reference_id,
            'created_at' => $row->created_at?->toJSON(),
        ])->all();

        return ApiResponse::data($items, meta: $this->paginationMeta($paginator));
    }

    public function walletToken(CreateWalletPaymentTokenRequest $request, int $student, ParentFinanceManager $manager): JsonResponse
    {
        Gate::authorize('wallet.view');
        $user = $request->user() ?? throw new AuthenticationException;
        $issued = $manager->issueWalletToken(
            Student::query()->findOrFail($student),
            (int) $user->id,
            $request->validated('max_amount_minor'),
        );

        return ApiResponse::data([
            'token' => $issued['token'],
            'expires_at' => $issued['model']->expires_at?->toJSON(),
            'single_use' => true,
            'scope' => $issued['model']->scope,
            'max_amount_minor' => Money::toMinor($issued['model']->max_amount, (string) config('payments.currency', 'SAR')),
        ], Response::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return ['pagination' => [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]];
    }
}
