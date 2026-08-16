<?php

namespace App\Http\Resources\Messaging;

use App\Models\ConversationThread;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class ConversationThreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ConversationThread) {
            throw new LogicException('ConversationThreadResource expects a ConversationThread model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'subject' => $this->resource->subject,
            'status' => $this->resource->status,
            'created_by_central_user_id' => (string) $this->resource->created_by_central_user_id,
            'participants' => $this->resource->participants->pluck('central_user_id')->map(fn ($id): string => (string) $id)->values()->all(),
        ];
    }
}
