<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'title', 'amount', 'currency', 'status'])]
class Fee extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_PAID = 'paid';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['student_id' => 'integer', 'amount' => 'decimal:2'];
    }
}
