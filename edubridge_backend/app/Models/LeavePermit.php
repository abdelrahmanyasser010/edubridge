<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['student_id', 'parent_id', 'reason', 'requested_leave_at', 'status', 'reviewed_by_central_user_id', 'reviewed_at', 'review_note', 'gate_token_hash', 'gate_token_expires_at', 'used_at'])]
class LeavePermit extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_USED = 'used';

    public const STATUS_EXPIRED = 'expired';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'parent_id' => 'integer',
            'reviewed_by_central_user_id' => 'integer',
            'requested_leave_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'gate_token_expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
