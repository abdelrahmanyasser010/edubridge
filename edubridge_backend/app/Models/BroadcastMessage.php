<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['target', 'target_ids', 'channels', 'priority', 'status', 'notification_id', 'title', 'body', 'type', 'scheduled_at', 'sent_at', 'cancelled_at', 'cancelled_by_central_user_id', 'created_by_central_user_id'])]
class BroadcastMessage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'tenant';

    protected $table = 'broadcast_messages';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_ids' => 'array',
            'channels' => 'array',
            'notification_id' => 'integer',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancelled_by_central_user_id' => 'integer',
            'created_by_central_user_id' => 'integer',
        ];
    }
}
