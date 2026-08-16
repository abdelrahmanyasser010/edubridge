<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['finance_invoice_id', 'amount', 'method', 'paid_at', 'reference', 'notes', 'recorded_by_central_user_id'])]
class FinancePayment extends Model
{
    protected $connection = 'tenant';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FinanceInvoice::class, 'finance_invoice_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'finance_invoice_id' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'recorded_by_central_user_id' => 'integer',
        ];
    }
}
