<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'currency', 'cached_balance', 'version'])]
class Wallet extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'cached_balance' => 'decimal:2',
            'version' => 'integer',
        ];
    }
}
