<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['teaching_session_id', 'original_teacher_id', 'substitute_teacher_id', 'assigned_by_central_user_id', 'reason', 'status', 'response_note', 'responded_at'])]
class TeacherSubstitution extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'teaching_session_id' => 'integer',
            'original_teacher_id' => 'integer',
            'substitute_teacher_id' => 'integer',
            'assigned_by_central_user_id' => 'integer',
            'responded_at' => 'datetime',
        ];
    }
}
