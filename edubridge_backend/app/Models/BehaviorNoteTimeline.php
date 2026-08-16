<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['behavior_note_id', 'from_status', 'to_status', 'actor_central_user_id', 'note'])]
class BehaviorNoteTimeline extends Model
{
    protected $connection = 'tenant';

    protected $table = 'behavior_note_timeline';

    public function behaviorNote(): BelongsTo
    {
        return $this->belongsTo(BehaviorNote::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'behavior_note_id' => 'integer',
            'actor_central_user_id' => 'integer',
        ];
    }
}
