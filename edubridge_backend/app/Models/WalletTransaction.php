<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['wallet_id', 'type', 'amount', 'balance_after', 'reference_type', 'reference_id', 'actor_central_user_id'])]
class WalletTransaction extends Model
{
    public const TYPE_TOP_UP = 'top_up';

    public const TYPE_DEDUCT = 'deduct';

    public const TYPE_REFUND_REVERSAL = 'refund_reversal';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'actor_central_user_id' => 'integer',
        ];
    }
}
