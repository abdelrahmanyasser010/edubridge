<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['academic_term_id', 'allocation_id', 'title', 'type', 'max_score', 'weight', 'status', 'submitted_at', 'approved_by_central_user_id', 'approved_at', 'published_at', 'locked_at'])]
class Assessment extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_LOCKED = 'locked';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'academic_term_id' => 'integer',
            'allocation_id' => 'integer',
            'max_score' => 'decimal:2',
            'weight' => 'decimal:2',
            'approved_by_central_user_id' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'locked_at' => 'datetime',
        ];
    }
}
