<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['assignment_id', 'student_id', 'submitted_by_central_user_id', 'file_id', 'status', 'submitted_at', 'version'])]
class AssignmentSubmission extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    protected $connection = 'tenant';

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assignment_id' => 'integer',
            'student_id' => 'integer',
            'submitted_by_central_user_id' => 'integer',
            'file_id' => 'integer',
            'submitted_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function submittedAtString(): string
    {
        return Carbon::parse($this->submitted_at)->toJSON();
    }
}
