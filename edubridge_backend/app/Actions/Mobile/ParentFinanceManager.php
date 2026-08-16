<?php

namespace App\Actions\Mobile;

use App\Actions\Wallet\WalletLedger;
use App\Models\FinanceInvoice;
use App\Models\Student;
use App\Models\Wallet;
use App\Models\WalletPaymentToken;
use App\Models\WalletTransaction;
use App\Support\Money;
use App\Support\ParentStudentAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ParentFinanceManager
{
    public function __construct(
        private readonly ParentStudentAccess $access,
        private readonly WalletLedger $walletLedger,
    ) {}

    /** @return array<string, mixed> */
    public function summary(Student $student, int $centralUserId): array
    {
        $student = $this->access->student($student->id, $centralUserId);
        $invoices = FinanceInvoice::query()
            ->where('student_id', $student->id)
            ->whereNotIn('status', [FinanceInvoice::STATUS_CANCELLED, FinanceInvoice::STATUS_PAID])
            ->get(['total', 'paid_total', 'currency', 'due_date', 'status']);
        $currency = (string) ($invoices->first()?->currency ?? config('payments.currency', 'SAR'));
        $wallet = Wallet::query()->where('student_id', $student->id)->first();
        $outstandingMinor = $invoices->sum(fn (FinanceInvoice $invoice): int => Money::toMinor(max(0, (float) $invoice->total - (float) $invoice->paid_total), $invoice->currency));
        $overdueMinor = $invoices->filter(fn (FinanceInvoice $invoice): bool => $invoice->status === FinanceInvoice::STATUS_OVERDUE || $invoice->due_date?->isPast())
            ->sum(fn (FinanceInvoice $invoice): int => Money::toMinor(max(0, (float) $invoice->total - (float) $invoice->paid_total), $invoice->currency));
        $nextDue = $invoices->sortBy('due_date')->first()?->due_date?->toDateString();

        return [
            'currency' => $currency,
            'total_due_minor' => (int) $outstandingMinor,
            'overdue_minor' => (int) $overdueMinor,
            'next_due_date' => $nextDue,
            'wallet_balance_minor' => Money::toMinor($wallet?->cached_balance ?? 0, (string) ($wallet?->currency ?? $currency)),
            'unpaid_invoices_count' => $invoices->count(),
        ];
    }

    /** @return LengthAwarePaginator<int, FinanceInvoice> */
    public function invoices(Student $student, int $centralUserId, array $filters): LengthAwarePaginator
    {
        $student = $this->access->student($student->id, $centralUserId);

        return FinanceInvoice::query()
            ->where('student_id', $student->id)
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $query, mixed $from) => $query->whereDate('issue_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, mixed $to) => $query->whereDate('issue_date', '<=', $to))
            ->with(['lines', 'payments'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function invoice(Student $student, FinanceInvoice $invoice, int $centralUserId): FinanceInvoice
    {
        $student = $this->access->student($student->id, $centralUserId);
        if ($invoice->student_id !== $student->id) {
            throw new NotFoundHttpException;
        }

        return $invoice->load(['lines', 'payments']);
    }

    /** @return array<string, mixed> */
    public function wallet(Student $student, int $centralUserId): array
    {
        $student = $this->access->student($student->id, $centralUserId);
        $currency = (string) config('payments.currency', 'SAR');
        $wallet = $this->walletLedger->walletFor($student, $currency);

        return [
            'id' => (string) $wallet->id,
            'student_id' => (string) $student->id,
            'currency' => $wallet->currency,
            'available_balance_minor' => Money::toMinor($wallet->cached_balance, $wallet->currency),
            'status' => 'active',
            'version' => (int) $wallet->version,
        ];
    }

    /** @return LengthAwarePaginator<int, WalletTransaction> */
    public function walletTransactions(Student $student, int $centralUserId, int $perPage = 25): LengthAwarePaginator
    {
        $student = $this->access->student($student->id, $centralUserId);
        $wallet = Wallet::query()->where('student_id', $student->id)->first();

        if ($wallet === null) {
            return WalletTransaction::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        return WalletTransaction::query()->where('wallet_id', $wallet->id)->orderByDesc('id')->paginate($perPage);
    }

    /** @return array{token:string,model:WalletPaymentToken} */
    public function issueWalletToken(Student $student, int $centralUserId, ?int $maxAmountMinor = null): array
    {
        $student = $this->access->student($student->id, $centralUserId);
        $currency = (string) config('payments.currency', 'SAR');
        $configuredMax = (int) config('payments.wallet.qr_max_purchase_minor', 50000);
        $maxAmountMinor = $maxAmountMinor === null ? $configuredMax : min($maxAmountMinor, $configuredMax);

        if ($maxAmountMinor <= 0) {
            throw new ConflictHttpException('Wallet token amount limit must be positive.');
        }

        $issued = $this->walletLedger->issuePaymentToken(
            $student,
            $currency,
            (float) Money::fromMinor($maxAmountMinor, $currency),
            (int) config('payments.wallet.qr_ttl_seconds', 60),
            'canteen',
        );

        return $issued;
    }

    /** @return array<string, mixed> */
    public function invoicePayload(FinanceInvoice $invoice): array
    {
        $currency = $invoice->currency;

        return [
            'id' => (string) $invoice->id,
            'number' => $invoice->invoice_number,
            'type' => $this->invoiceType($invoice),
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'currency' => $currency,
            'subtotal_minor' => Money::toMinor($invoice->subtotal, $currency),
            'discount_minor' => Money::toMinor($invoice->discount_total, $currency),
            'tax_minor' => Money::toMinor($invoice->tax_total, $currency),
            'total_minor' => Money::toMinor($invoice->total, $currency),
            'paid_minor' => Money::toMinor($invoice->paid_total, $currency),
            'due_minor' => Money::toMinor(max(0, (float) $invoice->total - (float) $invoice->paid_total), $currency),
            'status' => $invoice->status,
            'notes' => $invoice->notes,
            'line_items' => $invoice->relationLoaded('lines') ? $invoice->lines->map(fn ($line): array => [
                'id' => (string) $line->id,
                'title' => $line->title,
                'amount_minor' => Money::toMinor($line->amount, $currency),
            ])->values()->all() : [],
            'payments' => $invoice->relationLoaded('payments') ? $invoice->payments->map(fn ($payment): array => [
                'id' => (string) $payment->id,
                'amount_minor' => Money::toMinor($payment->amount, $currency),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paid_at' => $payment->paid_at?->toJSON(),
            ])->values()->all() : [],
        ];
    }

    private function invoiceType(FinanceInvoice $invoice): string
    {
        $text = strtolower(($invoice->notes ?? '').' '.($invoice->lines->first()?->title ?? ''));
        if (str_contains($text, 'activity')) {
            return 'activity';
        }
        if (str_contains($text, 'transport') || str_contains($text, 'bus')) {
            return 'transport';
        }
        if (str_contains($text, 'tuition') || str_contains($text, 'school fee')) {
            return 'tuition';
        }

        return 'other';
    }
}
