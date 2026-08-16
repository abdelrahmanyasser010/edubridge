<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['support_ticket_id', 'author_central_user_id', 'body'])]
class SupportTicketReply extends Model
{
    protected $connection = 'tenant';

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    protected function casts(): array
    {
        return [
            'support_ticket_id' => 'integer',
            'author_central_user_id' => 'integer',
        ];
    }
}
