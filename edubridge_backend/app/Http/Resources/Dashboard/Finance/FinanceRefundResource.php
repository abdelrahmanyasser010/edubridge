<?php

namespace App\Http\Resources\Dashboard\Finance;

use App\Models\FinancePayment;
use App\Models\FinanceRefund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class FinanceRefundResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof FinanceRefund) {
            throw new LogicException('FinanceRefundResource expects a FinanceRefund model.');
        }

        $payment = $this->resource->payment;

        return [
            'id' => (string) $this->resource->id,
            'payment_id' => (string) $this->resource->finance_payment_id,
            'invoice_id' => $payment instanceof FinancePayment ? (string) $payment->finance_invoice_id : null,
            'amount' => $this->resource->amount,
            'currency' => $this->resource->currency,
            'status' => $this->resource->status,
            'reason' => $this->resource->reason,
            'reference' => $this->resource->reference,
            'created_by_central_user_id' => $this->resource->created_by_central_user_id === null ? null : (string) $this->resource->created_by_central_user_id,
            'created_at' => $this->resource->created_at === null ? null : Carbon::parse($this->resource->created_at)->toJSON(),
        ];
    }
}
