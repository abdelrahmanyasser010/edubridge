<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['notification_id', 'central_user_id', 'channel', 'status', 'attempts', 'delivered_at', 'read_at', 'failed_at', 'last_error'])]
class NotificationDelivery extends Model
{
    public const CHANNEL_DATABASE = 'database';

    public const CHANNEL_PUSH = 'push';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    protected $connection = 'tenant';

    public function notification(): BelongsTo
    {
        return $this->belongsTo(NotificationMessage::class, 'notification_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'notification_id' => 'integer',
            'central_user_id' => 'integer',
            'attempts' => 'integer',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function deliveredAtString(): ?string
    {
        return $this->delivered_at === null ? null : Carbon::parse($this->delivered_at)->toJSON();
    }

    public function readAtString(): ?string
    {
        return $this->read_at === null ? null : Carbon::parse($this->read_at)->toJSON();
    }
}
