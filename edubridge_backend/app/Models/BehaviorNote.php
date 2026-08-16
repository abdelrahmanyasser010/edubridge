<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable(['student_id', 'allocation_id', 'created_by_teacher_id', 'title', 'body', 'severity', 'status', 'reviewed_by_central_user_id', 'reviewed_at', 'published_at', 'acknowledged_at', 'resolved_at', 'version'])]
class BehaviorNote extends Model
{
    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_REJECTED = 'rejected';

    protected $connection = 'tenant';

    public function timeline(): HasMany
    {
        return $this->hasMany(BehaviorNoteTimeline::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(BehaviorRecommendation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'allocation_id' => 'integer',
            'created_by_teacher_id' => 'integer',
            'reviewed_by_central_user_id' => 'integer',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function publishedAtString(): ?string
    {
        return $this->published_at === null ? null : Carbon::parse($this->published_at)->toJSON();
    }
}
