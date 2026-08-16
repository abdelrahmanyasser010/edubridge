<?php

namespace App\Http\Resources\Mobile;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof SupportTicket) {
            throw new LogicException('SupportTicketResource expects a SupportTicket model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'category_key' => $this->resource->category_key,
            'subject' => $this->resource->subject,
            'status' => $this->resource->status,
            'replies_count' => (int) ($this->resource->replies_count ?? ($this->resource->relationLoaded('replies') ? $this->resource->replies->count() : 0)),
            'messages' => $this->resource->relationLoaded('replies') ? $this->resource->replies->map(fn ($reply): array => [
                'id' => (string) $reply->id,
                'author_central_user_id' => (string) $reply->author_central_user_id,
                'body' => $reply->body,
                'created_at' => $reply->created_at?->toJSON(),
            ])->values()->all() : null,
            'created_at' => $this->resource->created_at?->toJSON(),
            'updated_at' => $this->resource->updated_at?->toJSON(),
            'resolved_at' => $this->resource->resolved_at?->toJSON(),
        ];
    }
}
