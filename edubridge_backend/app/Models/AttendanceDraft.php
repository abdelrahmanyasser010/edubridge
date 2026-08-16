<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['teaching_session_id', 'teacher_id', 'records', 'version'])]
class AttendanceDraft extends Model
{
    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'teaching_session_id' => 'integer',
            'teacher_id' => 'integer',
            'records' => 'array',
            'version' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TeachingSession::class, 'teaching_session_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
