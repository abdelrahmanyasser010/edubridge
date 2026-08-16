<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['opened_by_central_user_id', 'category_key', 'subject', 'status', 'resolved_at', 'closed_at'])]
class SupportTicket extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    protected $connection = 'tenant';

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class);
    }

    protected function casts(): array
    {
        return [
            'opened_by_central_user_id' => 'integer',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
