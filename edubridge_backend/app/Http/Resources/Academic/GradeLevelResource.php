<?php

namespace App\Http\Resources\Academic;

use App\Models\GradeLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class GradeLevelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof GradeLevel) {
            throw new LogicException('GradeLevelResource expects a GradeLevel model.');
        }

        return [
            'id' => (string) $this->resource->id,
            'name' => $this->resource->name,
            'code' => $this->resource->code,
            'sort_order' => $this->resource->sort_order,
            'status' => $this->resource->status,
            'subjects' => $this->resource->relationLoaded('subjects')
                ? SubjectResource::collection($this->resource->subjects)->resolve($request)
                : [],
            'sections' => $this->resource->relationLoaded('sections')
                ? SectionResource::collection($this->resource->sections)->resolve($request)
                : [],
        ];
    }
}
