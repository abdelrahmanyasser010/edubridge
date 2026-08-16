<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['payment_session_id', 'amount', 'currency', 'status', 'reference_id'])]
class PaymentRefund extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payment_session_id' => 'integer', 'amount' => 'decimal:2'];
    }
}
