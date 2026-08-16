<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['wallet_id', 'token_hash', 'max_amount', 'scope', 'expires_at', 'used_at'])]
class WalletPaymentToken extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'max_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
