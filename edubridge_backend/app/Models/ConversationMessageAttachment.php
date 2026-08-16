<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_message_id', 'file_id'])]
class ConversationMessageAttachment extends Model
{
    protected $connection = 'tenant';

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'conversation_message_id' => 'integer',
            'file_id' => 'integer',
        ];
    }
}
