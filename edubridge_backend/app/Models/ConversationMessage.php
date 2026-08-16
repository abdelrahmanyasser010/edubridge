<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['conversation_thread_id', 'sender_central_user_id', 'body'])]
class ConversationMessage extends Model
{
    protected $connection = 'tenant';

    public function attachments(): HasMany
    {
        return $this->hasMany(ConversationMessageAttachment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'conversation_thread_id' => 'integer',
            'sender_central_user_id' => 'integer',
        ];
    }
}
