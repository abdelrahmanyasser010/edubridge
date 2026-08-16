<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['behavior_note_id', 'created_by_central_user_id', 'body', 'status'])]
class BehaviorRecommendation extends Model
{
    public const STATUS_PUBLISHED = 'published';

    protected $connection = 'tenant';

    public function behaviorNote(): BelongsTo
    {
        return $this->belongsTo(BehaviorNote::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'behavior_note_id' => 'integer',
            'created_by_central_user_id' => 'integer',
        ];
    }
}
