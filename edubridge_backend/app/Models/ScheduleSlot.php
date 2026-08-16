<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['academic_term_id', 'allocation_id', 'weekday', 'starts_at', 'ends_at', 'room', 'status'])]
class ScheduleSlot extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $connection = 'tenant';

    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(TeacherSectionSubject::class, 'allocation_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TeachingSession::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'academic_term_id' => 'integer',
            'allocation_id' => 'integer',
            'weekday' => 'integer',
        ];
    }
}
