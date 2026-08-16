<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['student_id', 'parent_id', 'file_id', 'starts_on', 'ends_on', 'reason', 'status', 'reviewed_by_central_user_id', 'reviewed_at', 'review_note'])]
class MedicalExcuse extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $connection = 'tenant';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'parent_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileObject::class, 'file_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'parent_id' => 'integer',
            'file_id' => 'integer',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'reviewed_by_central_user_id' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function startsOnString(): string
    {
        return Carbon::parse($this->starts_on)->toDateString();
    }

    public function endsOnString(): string
    {
        return Carbon::parse($this->ends_on)->toDateString();
    }
}
