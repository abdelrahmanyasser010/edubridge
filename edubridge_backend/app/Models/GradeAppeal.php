<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['grade_entry_id', 'student_id', 'parent_id', 'reason', 'status', 'reviewed_by_central_user_id', 'review_note', 'reviewed_at'])]
class GradeAppeal extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CORRECTED = 'corrected';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grade_entry_id' => 'integer',
            'student_id' => 'integer',
            'parent_id' => 'integer',
            'reviewed_by_central_user_id' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
