<?php

namespace App\Http\Resources\Messaging;

use App\Models\ConversationMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class ConversationMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ConversationMessage) {
            throw new LogicException('ConversationMessageResource expects a ConversationMessage model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'conversation_thread_id' => (string) $this->resource->conversation_thread_id,
            'sender_central_user_id' => (string) $this->resource->sender_central_user_id,
            'body' => $this->resource->body,
            'attachments' => $this->resource->attachments->pluck('file_id')->map(fn ($id): string => (string) $id)->values()->all(),
            'created_at' => $this->resource->created_at?->toJSON(),
        ];
    }
}
