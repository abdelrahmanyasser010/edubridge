<?php

namespace App\Http\Resources\Dashboard\Finance;

use App\Models\FinancePayment;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class FinancePaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof FinancePayment) {
            throw new LogicException('FinancePaymentResource expects a FinancePayment model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'invoice_id' => (string) $this->resource->finance_invoice_id,
            'invoice_number' => $this->invoiceNumber(),
            'amount' => round((float) $this->resource->amount, 2),
            'method' => $this->resource->method,
            'paid_at' => $this->dateTimeString($this->resource->paid_at),
            'reference' => $this->resource->reference,
            'notes' => $this->resource->notes,
            'recorded_by_central_user_id' => $this->resource->recorded_by_central_user_id === null ? null : (string) $this->resource->recorded_by_central_user_id,
        ];
    }

    private function invoiceNumber(): ?string
    {
        if ($this->resource->relationLoaded('invoice')) {
            return $this->resource->invoice?->invoice_number;
        }

        $invoiceNumber = DB::connection('tenant')->table('finance_invoices')->where('id', $this->resource->finance_invoice_id)->value('invoice_number');

        return is_string($invoiceNumber) ? $invoiceNumber : null;
    }

    private function dateTimeString(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toISOString();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->toISOString();
        }

        return null;
    }
}
