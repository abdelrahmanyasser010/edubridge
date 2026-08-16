<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['finance_payment_id', 'amount', 'currency', 'status', 'reason', 'reference', 'created_by_central_user_id'])]
class FinanceRefund extends Model
{
    public const STATUS_COMPLETED = 'completed';

    protected $connection = 'tenant';

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FinancePayment::class, 'finance_payment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'finance_payment_id' => 'integer',
            'amount' => 'decimal:2',
            'created_by_central_user_id' => 'integer',
        ];
    }
}
