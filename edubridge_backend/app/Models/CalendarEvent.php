<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'description', 'type', 'starts_at', 'ends_at', 'all_day', 'audience_type', 'audience_ids', 'location', 'status', 'created_by_central_user_id'])]
class CalendarEvent extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'tenant';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'audience_ids' => 'array',
            'created_by_central_user_id' => 'integer',
        ];
    }
}
