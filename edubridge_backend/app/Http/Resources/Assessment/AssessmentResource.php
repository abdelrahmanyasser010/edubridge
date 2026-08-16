<?php

namespace App\Http\Resources\Assessment;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use LogicException;

class AssessmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof Assessment) {
            throw new LogicException('AssessmentResource expects an Assessment model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'academic_term_id' => (string) $this->resource->academic_term_id,
            'allocation_id' => (string) $this->resource->allocation_id,
            'title' => $this->resource->title,
            'type' => $this->resource->type,
            'max_score' => $this->resource->max_score,
            'weight' => $this->resource->weight,
            'status' => $this->resource->status,
            'submitted_at' => $this->resource->submitted_at === null ? null : Carbon::parse($this->resource->submitted_at)->toJSON(),
            'approved_by_central_user_id' => $this->resource->approved_by_central_user_id === null ? null : (string) $this->resource->approved_by_central_user_id,
            'approved_at' => $this->resource->approved_at === null ? null : Carbon::parse($this->resource->approved_at)->toJSON(),
            'published_at' => $this->resource->published_at === null ? null : Carbon::parse($this->resource->published_at)->toJSON(),
            'locked_at' => $this->resource->locked_at === null ? null : Carbon::parse($this->resource->locked_at)->toJSON(),
        ];
    }
}
