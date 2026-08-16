<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_thread_id', 'central_user_id', 'last_read_at'])]
class ConversationParticipant extends Model
{
    protected $connection = 'tenant';

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ConversationThread::class, 'conversation_thread_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'conversation_thread_id' => 'integer',
            'central_user_id' => 'integer',
            'last_read_at' => 'datetime',
        ];
    }
}
