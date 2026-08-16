<?php

namespace App\Http\Resources\Behavior;

use App\Models\BehaviorNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class BehaviorNoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof BehaviorNote) {
            throw new LogicException('BehaviorNoteResource expects a BehaviorNote model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'student_id' => (string) $this->resource->student_id,
            'allocation_id' => (string) $this->resource->allocation_id,
            'created_by_teacher_id' => (string) $this->resource->created_by_teacher_id,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'severity' => $this->resource->severity,
            'status' => $this->resource->status,
            'published_at' => $this->resource->publishedAtString(),
            'version' => $this->resource->version,
            'timeline' => $this->resource->timeline->map(fn ($item): array => [
                'from_status' => $item->getAttribute('from_status'),
                'to_status' => $item->getAttribute('to_status'),
                'actor_central_user_id' => $item->getAttribute('actor_central_user_id') === null ? null : (string) $item->getAttribute('actor_central_user_id'),
                'note' => $item->getAttribute('note'),
                'created_at' => Carbon::parse($item->getAttribute('created_at'))->toJSON(),
            ])->values()->all(),
            'recommendations' => $this->resource->recommendations->map(fn ($item): array => [
                'id' => (string) $item->getAttribute('id'),
                'body' => $item->getAttribute('body'),
                'status' => $item->getAttribute('status'),
                'created_by_central_user_id' => (string) $item->getAttribute('created_by_central_user_id'),
            ])->values()->all(),
        ];
    }
}
