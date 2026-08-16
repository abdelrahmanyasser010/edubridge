<?php

namespace App\Http\Resources\Operations;

use App\Models\ParentSummons;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class ParentSummonsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ParentSummons) {
            throw new LogicException('ParentSummonsResource expects a ParentSummons model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'student_id' => (string) $this->resource->student_id,
            'parent_id' => (string) $this->resource->parent_id,
            'scheduled_at' => Carbon::parse($this->resource->scheduled_at)->toJSON(),
            'reason' => $this->resource->reason,
            'status' => $this->resource->status,
            'response' => $this->resource->response,
            'response_note' => $this->resource->response_note,
            'responded_at' => $this->resource->responded_at === null ? null : Carbon::parse($this->resource->responded_at)->toJSON(),
        ];
    }
}
