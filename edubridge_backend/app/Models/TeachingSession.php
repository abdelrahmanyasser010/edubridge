<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['schedule_slot_id', 'allocation_id', 'session_date', 'starts_at', 'ends_at', 'status'])]
class TeachingSession extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'tenant';

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ScheduleSlot::class, 'schedule_slot_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(TeacherSectionSubject::class, 'allocation_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'schedule_slot_id' => 'integer',
            'allocation_id' => 'integer',
            'session_date' => 'date:Y-m-d',
        ];
    }

    public function sessionDateString(): string
    {
        return Carbon::parse($this->session_date)->toDateString();
    }
}
