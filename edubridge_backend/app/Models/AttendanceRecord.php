<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['teaching_session_id', 'student_id', 'status', 'recorded_by_teacher_id', 'submitted_at', 'revision'])]
class AttendanceRecord extends Model
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_EXCUSED = 'excused';

    protected $connection = 'tenant';

    public function session(): BelongsTo
    {
        return $this->belongsTo(TeachingSession::class, 'teaching_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'teaching_session_id' => 'integer',
            'student_id' => 'integer',
            'recorded_by_teacher_id' => 'integer',
            'submitted_at' => 'datetime',
            'revision' => 'integer',
        ];
    }
}
