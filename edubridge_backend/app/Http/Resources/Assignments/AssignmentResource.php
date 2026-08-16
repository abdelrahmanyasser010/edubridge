<?php

namespace App\Http\Resources\Assignments;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class AssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Assignment) {
            throw new LogicException('AssignmentResource expects an Assignment model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'allocation_id' => (string) $this->resource->allocation_id,
            'assigned_by_teacher_id' => (string) $this->resource->assigned_by_teacher_id,
            'title' => $this->resource->title,
            'body' => $this->resource->body,
            'due_at' => $this->resource->dueAtString(),
            'status' => $this->resource->status,
            'published_at' => $this->resource->publishedAtString(),
            'version' => $this->resource->version,
            'attachments' => $this->resource->attachments->pluck('file_id')->map(fn ($fileId): array => [
                'file_id' => (string) $fileId,
            ])->values()->all(),
        ];
    }
}
