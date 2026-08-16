<?php

namespace App\Actions\Finance;

use App\Models\FinanceDiscount;
use App\Models\FinanceInvoice;
use App\Models\FinanceInvoiceLine;
use App\Models\FinancePayment;
use App\Models\FinanceRefund;
use App\Models\Student;
use App\Support\AuditLogger;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinanceDashboardManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array<string, mixed> */
    public function summary(): array
    {
        $totals = DB::connection('tenant')
            ->table('finance_invoices')
            ->where('status', '!=', FinanceInvoice::STATUS_CANCELLED)
            ->selectRaw('COALESCE(SUM(total), 0) as total_due, COALESCE(SUM(paid_total), 0) as total_paid')
            ->first();

        $overdue = DB::connection('tenant')
            ->table('finance_invoices')
            ->where('status', '!=', FinanceInvoice::STATUS_CANCELLED)
            ->where('status', '!=', FinanceInvoice::STATUS_PAID)
            ->where('due_date', '<', today()->toDateString())
            ->selectRaw('COALESCE(SUM(total - paid_total), 0) as amount, COUNT(DISTINCT student_id) as students')
            ->first();

        $currency = DB::connection('tenant')->table('finance_invoices')->where('status', '!=', FinanceInvoice::STATUS_CANCELLED)->value('currency') ?? 'SAR';
        $totalDue = round((float) $totals->total_due, 2);
        $totalPaid = round((float) $totals->total_paid, 2);

        return [
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'overdue_amount' => round((float) $overdue->amount, 2),
            'overdue_students' => (int) $overdue->students,
            'collection_rate' => $totalDue <= 0 ? 0.0 : round(($totalPaid / $totalDue) * 100, 2),
            'currency' => (string) $currency,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, FinanceInvoice>
     */
    public function invoices(array $filters): LengthAwarePaginator
    {
        return $this->invoiceQuery($filters)
            ->with(['student', 'lines'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @param array<string, mixed> $data */
    public function createInvoice(array $data): FinanceInvoice
    {
        return DB::connection('tenant')->transaction(function () use ($data): FinanceInvoice {
            $invoice = FinanceInvoice::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'student_id' => (int) $data['student_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $data['currency'] ?? 'SAR',
                'notes' => $data['notes'] ?? null,
                ...$this->amounts($data),
            ]);

            $this->replaceLines($invoice, $data['lines']);
            $this->refreshStatus($invoice);
            $invoice->refresh()->load(['student', 'lines']);

            $this->audit->record('finance.invoice.created', FinanceInvoice::class, (string) $invoice->id, null, [
                'invoice_number' => $invoice->invoice_number,
                'student_id' => (string) $invoice->student_id,
                'total' => (string) $invoice->total,
            ]);

            return $invoice;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateInvoice(FinanceInvoice $invoice, array $data): FinanceInvoice
    {
        return DB::connection('tenant')->transaction(function () use ($invoice, $data): FinanceInvoice {
            $before = $invoice->only(['issue_date', 'due_date', 'subtotal', 'discount_total', 'tax_total', 'total', 'paid_total', 'status', 'currency', 'notes']);

            if ($invoice->status === FinanceInvoice::STATUS_CANCELLED) {
                throw new ConflictHttpException('Cancelled invoices cannot be updated.');
            }

            $updates = [];
            foreach (['issue_date', 'due_date', 'currency', 'notes', 'status'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            if (array_key_exists('lines', $data) || array_key_exists('discount', $data) || array_key_exists('tax', $data)) {
                $lineData = $data['lines'] ?? DB::connection('tenant')
                    ->table('finance_invoice_lines')
                    ->where('finance_invoice_id', $invoice->id)
                    ->orderBy('sort_order')
                    ->get(['title', 'amount'])
                    ->map(fn (object $line): array => [
                        'title' => (string) $line->title,
                        'amount' => (float) $line->amount,
                    ])->all();
                $amounts = $this->amounts([
                    'lines' => $lineData,
                    'discount' => $data['discount'] ?? $invoice->discount_total,
                    'tax' => $data['tax'] ?? $invoice->tax_total,
                ]);

                if ((float) $amounts['total'] < (float) $invoice->paid_total) {
                    throw new ConflictHttpException('Invoice total cannot be less than the amount already paid.');
                }

                $updates = array_merge($updates, $amounts);

                if (array_key_exists('lines', $data)) {
                    $this->replaceLines($invoice, $data['lines']);
                }
            }

            $invoice->fill($updates)->save();

            if (($data['status'] ?? null) !== FinanceInvoice::STATUS_CANCELLED) {
                $this->refreshStatus($invoice);
            }

            $invoice->refresh()->load(['student', 'lines']);

            $this->audit->record('finance.invoice.updated', FinanceInvoice::class, (string) $invoice->id, $before, $invoice->only(['issue_date', 'due_date', 'subtotal', 'discount_total', 'tax_total', 'total', 'paid_total', 'status', 'currency', 'notes']));

            return $invoice;
        });
    }

    public function cancelInvoice(FinanceInvoice $invoice): FinanceInvoice
    {
        return DB::connection('tenant')->transaction(function () use ($invoice): FinanceInvoice {
            $before = $invoice->only(['status']);
            $invoice->forceFill(['status' => FinanceInvoice::STATUS_CANCELLED])->save();
            $invoice->refresh()->load(['student', 'lines']);

            $this->audit->record('finance.invoice.cancelled', FinanceInvoice::class, (string) $invoice->id, $before, ['status' => $invoice->status]);

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, FinancePayment>
     */
    public function payments(array $filters): LengthAwarePaginator
    {
        return FinancePayment::query()
            ->when($filters['invoice_id'] ?? null, fn (Builder $query, mixed $invoiceId) => $query->where('finance_invoice_id', $invoiceId))
            ->when($filters['student_id'] ?? null, function (Builder $query, mixed $studentId): void {
                $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('student_id', $studentId));
            })
            ->when($filters['from'] ?? null, fn (Builder $query, mixed $from) => $query->whereDate('paid_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, mixed $to) => $query->whereDate('paid_at', '<=', $to))
            ->with('invoice')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @param array<string, mixed> $data */
    public function recordPayment(array $data, ?int $actorId): FinancePayment
    {
        return DB::connection('tenant')->transaction(function () use ($data, $actorId): FinancePayment {
            $invoice = FinanceInvoice::query()->lockForUpdate()->findOrFail((int) $data['invoice_id']);

            if ($invoice->status === FinanceInvoice::STATUS_CANCELLED) {
                throw new ConflictHttpException('Cannot record a payment against a cancelled invoice.');
            }

            $remaining = max(0, (float) $invoice->total - (float) $invoice->paid_total);
            if ((float) $data['amount'] > $remaining) {
                throw new ConflictHttpException('Payment amount exceeds invoice remaining balance.');
            }

            $payment = FinancePayment::query()->create([
                'finance_invoice_id' => $invoice->id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'paid_at' => Carbon::parse((string) $data['paid_at']),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by_central_user_id' => $actorId,
            ]);

            $invoice->forceFill([
                'paid_total' => round((float) $invoice->paid_total + (float) $payment->amount, 2),
            ])->save();
            $this->refreshStatus($invoice);

            $payment->refresh()->load('invoice');
            $this->audit->record('finance.payment.recorded', FinancePayment::class, (string) $payment->id, null, [
                'invoice_id' => (string) $invoice->id,
                'amount' => (string) $payment->amount,
                'method' => $payment->method,
                'reference' => $payment->reference,
            ]);

            return $payment;
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, FinanceRefund>
     */
    public function refunds(array $filters): LengthAwarePaginator
    {
        return FinanceRefund::query()
            ->when($filters['payment_id'] ?? null, fn (Builder $query, mixed $paymentId) => $query->where('finance_payment_id', $paymentId))
            ->when($filters['invoice_id'] ?? null, function (Builder $query, mixed $invoiceId): void {
                $query->whereHas('payment', fn (Builder $paymentQuery) => $paymentQuery->where('finance_invoice_id', $invoiceId));
            })
            ->when($filters['from'] ?? null, fn (Builder $query, mixed $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, mixed $to) => $query->whereDate('created_at', '<=', $to))
            ->with('payment')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @param array<string, mixed> $data */
    public function refundPayment(FinancePayment $payment, array $data, ?int $actorId): FinanceRefund
    {
        return DB::connection('tenant')->transaction(function () use ($payment, $data, $actorId): FinanceRefund {
            $payment = FinancePayment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = FinanceInvoice::query()->lockForUpdate()->findOrFail($payment->finance_invoice_id);

            if (($data['reference'] ?? null) !== null) {
                $existing = FinanceRefund::query()->where('reference', $data['reference'])->with('payment')->first();
                if ($existing instanceof FinanceRefund) {
                    return $existing;
                }
            }

            $refunded = (float) FinanceRefund::query()
                ->where('finance_payment_id', $payment->id)
                ->where('status', FinanceRefund::STATUS_COMPLETED)
                ->sum('amount');
            $refundable = max(0, (float) $payment->amount - $refunded);

            if ((float) $data['amount'] > $refundable) {
                throw new ConflictHttpException('Refund amount exceeds remaining refundable payment amount.');
            }

            $refund = FinanceRefund::query()->create([
                'finance_payment_id' => $payment->id,
                'amount' => $data['amount'],
                'currency' => $invoice->currency,
                'status' => FinanceRefund::STATUS_COMPLETED,
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
                'created_by_central_user_id' => $actorId,
            ]);

            $invoice->forceFill([
                'paid_total' => round(max(0, (float) $invoice->paid_total - (float) $refund->amount), 2),
            ])->save();
            $this->refreshStatus($invoice);

            $this->audit->record('finance.refund.created', FinanceRefund::class, (string) $refund->id, null, [
                'payment_id' => (string) $payment->id,
                'invoice_id' => (string) $invoice->id,
                'amount' => (string) $refund->amount,
                'reference' => $refund->reference,
            ]);

            return $refund->refresh()->load('payment');
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, FinanceDiscount>
     */
    public function discounts(array $filters): LengthAwarePaginator
    {
        return FinanceDiscount::query()
            ->when($filters['student_id'] ?? null, fn (Builder $query, mixed $studentId) => $query->where('student_id', $studentId))
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status) => $query->where('status', $status))
            ->with('student')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25));
    }

    /** @param array<string, mixed> $data */
    public function createDiscount(array $data): FinanceDiscount
    {
        $discount = FinanceDiscount::query()->create($data)->refresh()->load('student');
        $this->audit->record('finance.discount.created', FinanceDiscount::class, (string) $discount->id, null, $discount->only(['student_id', 'title', 'amount', 'type', 'status']));

        return $discount;
    }

    /** @param array<string, mixed> $data */
    public function updateDiscount(FinanceDiscount $discount, array $data): FinanceDiscount
    {
        $before = $discount->only(['student_id', 'title', 'amount', 'type', 'status', 'valid_from', 'valid_until', 'notes']);
        $discount->fill($data)->save();
        $discount->refresh()->load('student');
        $this->audit->record('finance.discount.updated', FinanceDiscount::class, (string) $discount->id, $before, $discount->only(['student_id', 'title', 'amount', 'type', 'status', 'valid_from', 'valid_until', 'notes']));

        return $discount;
    }

    public function archiveDiscount(FinanceDiscount $discount): FinanceDiscount
    {
        return $this->updateDiscount($discount, ['status' => FinanceDiscount::STATUS_ARCHIVED]);
    }

    /** @param array<string, mixed> $filters */
    public function collectionsReport(array $filters): array
    {
        return DB::connection('tenant')
            ->table('finance_payments')
            ->when($filters['from'] ?? null, fn ($query, mixed $from) => $query->whereDate('paid_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, mixed $to) => $query->whereDate('paid_at', '<=', $to))
            ->selectRaw('DATE(paid_at) as date, method, COALESCE(SUM(amount), 0) as total, COUNT(*) as count')
            ->groupByRaw('DATE(paid_at), method')
            ->orderByRaw('DATE(paid_at) DESC')
            ->get()
            ->map(fn (object $row): array => [
                'date' => (string) $row->date,
                'method' => (string) $row->method,
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /** @param array<string, mixed> $filters */
    public function outstandingReport(array $filters): array
    {
        return DB::connection('tenant')
            ->table('finance_invoices')
            ->join('students', 'students.id', '=', 'finance_invoices.student_id')
            ->when($filters['from'] ?? null, fn ($query, mixed $from) => $query->whereDate('finance_invoices.issue_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, mixed $to) => $query->whereDate('finance_invoices.issue_date', '<=', $to))
            ->where('finance_invoices.status', '!=', FinanceInvoice::STATUS_CANCELLED)
            ->whereColumn('finance_invoices.paid_total', '<', 'finance_invoices.total')
            ->orderBy('finance_invoices.due_date')
            ->limit((int) ($filters['per_page'] ?? 100))
            ->get([
                'finance_invoices.id',
                'finance_invoices.invoice_number',
                'finance_invoices.student_id',
                'students.full_name as student_name',
                'finance_invoices.due_date',
                'finance_invoices.total',
                'finance_invoices.paid_total',
                'finance_invoices.status',
                'finance_invoices.currency',
            ])
            ->map(fn (object $invoice): array => [
                'invoice_id' => (string) $invoice->id,
                'invoice_number' => (string) $invoice->invoice_number,
                'student_id' => (string) $invoice->student_id,
                'student_name' => (string) $invoice->student_name,
                'due_date' => $this->dateString($invoice->due_date),
                'remaining' => round((float) $invoice->total - (float) $invoice->paid_total, 2),
                'status' => (string) $invoice->status,
                'currency' => (string) $invoice->currency,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    public function studentStatement(Student $student): array
    {
        $invoices = FinanceInvoice::query()
            ->where('student_id', $student->id)
            ->with(['lines', 'payments'])
            ->orderByDesc('issue_date')
            ->get();

        return [
            'student' => [
                'id' => (string) $student->id,
                'full_name' => $student->full_name,
                'admission_number' => $student->admission_number,
            ],
            'summary' => [
                'total_due' => round((float) $invoices->sum('total'), 2),
                'total_paid' => round((float) $invoices->sum('paid_total'), 2),
                'remaining' => round((float) $invoices->sum(fn ($invoice): float => max(0, (float) $invoice->total - (float) $invoice->paid_total)), 2),
            ],
            'invoices' => $invoices->map(fn (FinanceInvoice $invoice): array => [
                'id' => (string) $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'issue_date' => $this->dateString($invoice->issue_date),
                'due_date' => $this->dateString($invoice->due_date),
                'total' => round((float) $invoice->total, 2),
                'paid' => round((float) $invoice->paid_total, 2),
                'remaining' => round(max(0, (float) $invoice->total - (float) $invoice->paid_total), 2),
                'status' => $invoice->status,
                'currency' => $invoice->currency,
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<FinanceInvoice>
     */
    private function invoiceQuery(array $filters): Builder
    {
        return FinanceInvoice::query()
            ->when($filters['student_id'] ?? null, fn (Builder $query, mixed $studentId) => $query->where('student_id', $studentId))
            ->when($filters['status'] ?? null, fn (Builder $query, mixed $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn (Builder $query, mixed $from) => $query->whereDate('issue_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, mixed $to) => $query->whereDate('issue_date', '<=', $to));
    }

    /** @param array<string, mixed> $data */
    private function amounts(array $data): array
    {
        $subtotal = collect($data['lines'])->sum(fn (array $line): float => (float) $line['amount']);
        $discount = (float) ($data['discount'] ?? 0);
        $tax = (float) ($data['tax'] ?? 0);
        $total = max(0, $subtotal - $discount + $tax);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'tax_total' => round($tax, 2),
            'total' => round($total, 2),
        ];
    }

    /** @param list<array<string, mixed>> $lines */
    private function replaceLines(FinanceInvoice $invoice, array $lines): void
    {
        FinanceInvoiceLine::query()->where('finance_invoice_id', $invoice->id)->delete();

        foreach ($lines as $index => $line) {
            FinanceInvoiceLine::query()->create([
                'finance_invoice_id' => $invoice->id,
                'title' => $line['title'],
                'amount' => $line['amount'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function refreshStatus(FinanceInvoice $invoice): void
    {
        if ($invoice->status === FinanceInvoice::STATUS_CANCELLED) {
            return;
        }

        $paid = (float) $invoice->paid_total;
        $total = (float) $invoice->total;
        $status = FinanceInvoice::STATUS_OPEN;

        if ($paid >= $total && $total > 0) {
            $status = FinanceInvoice::STATUS_PAID;
        } elseif ($paid > 0) {
            $status = FinanceInvoice::STATUS_PARTIAL;
        } elseif ($this->isPastDate($invoice->due_date)) {
            $status = FinanceInvoice::STATUS_OVERDUE;
        }

        $invoice->forceFill(['status' => $status])->save();
    }

    private function nextInvoiceNumber(): string
    {
        $next = ((int) FinanceInvoice::query()->max('id')) + 1;

        return 'INV-'.now()->format('Y').'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function dateString(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->toDateString();
        }

        return null;
    }

    private function isPastDate(mixed $value): bool
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->isPast();
        }

        return is_string($value) && $value !== '' && Carbon::parse($value)->isPast();
    }
}
