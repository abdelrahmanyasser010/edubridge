<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'parent_id', 'created_by_central_user_id', 'scheduled_at', 'reason', 'status', 'response', 'response_note', 'responded_at'])]
class ParentSummons extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RESPONDED = 'responded';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'tenant';

    protected $table = 'parent_summons';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'parent_id' => 'integer',
            'created_by_central_user_id' => 'integer',
            'scheduled_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }
}
